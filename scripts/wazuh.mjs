// Orquesta el SIEM (Wazuh single-node oficial) con la configuración de NovaTech.
//   node scripts/wazuh.mjs up [--dry-run]       clona Wazuh, genera certificados y configuración, y levanta todo
//   node scripts/wazuh.mjs logtest              prueba las reglas con ops/wazuh/samples.log (líneas reales)
//   node scripts/wazuh.mjs status               estado de los contenedores de Wazuh
//   node scripts/wazuh.mjs down [--purge]       detiene Wazuh (--purge borra también sus datos)
// Variables opcionales: WAZUH_STACK_DIR (carpeta single-node ya clonada), WAZUH_VERSION.

import { spawnSync } from 'node:child_process'
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs'
import { dirname, join, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..')
const OPS = join(ROOT, 'ops', 'wazuh')
const VERSION = process.env.WAZUH_VERSION ?? 'v4.14.9'
const UPSTREAM = join(OPS, 'upstream')
const STACK = process.env.WAZUH_STACK_DIR ?? join(UPSTREAM, 'single-node')
const GENERATED = join(OPS, 'generated')
const VOLUMES = ['novatech_applogs', 'novatech_waflogs']

const [command = 'help', ...flags] = process.argv.slice(2)
const dryRun = flags.includes('--dry-run')
const fwd = (p) => p.replaceAll('\\', '/') // Docker Compose acepta rutas con "/" también en Windows

function run(cmd, args, { cwd = ROOT, input, allowFail = false } = {}) {
  console.log(`  $ ${cmd} ${args.join(' ')}${cwd !== ROOT ? `   (en ${cwd})` : ''}`)
  if (dryRun) return { status: 0, stdout: '' }
  const res = spawnSync(cmd, args, { cwd, input, stdio: input ? ['pipe', 'inherit', 'inherit'] : 'inherit', encoding: 'utf8' })
  if (res.status !== 0 && !allowFail) {
    console.error(`\nFalló: ${cmd} ${args.join(' ')}`)
    process.exit(res.status ?? 1)
  }
  return res
}

function capture(cmd, args, cwd) {
  const res = spawnSync(cmd, args, { cwd, encoding: 'utf8' })
  return (res.stdout ?? '').trim()
}

const composeFiles = () => ['-f', join(STACK, 'docker-compose.yml'), '-f', join(GENERATED, 'docker-compose.novatech.yml')]

function generate() {
  const upstreamConf = join(STACK, 'config', 'wazuh_cluster', 'wazuh_manager.conf')
  if (!existsSync(upstreamConf)) {
    console.log(`  (se omite la generación: no existe ${upstreamConf})`)
    return false
  }
  mkdirSync(GENERATED, { recursive: true })
  // El manager admite varios bloques <ossec_config>: se añade uno al final con los logs de NovaTech.
  const conf = readFileSync(upstreamConf, 'utf8').trimEnd() + '\n\n' + readFileSync(join(OPS, 'localfiles.xml'), 'utf8')
  writeFileSync(join(GENERATED, 'wazuh_manager.conf'), conf)

  const override = `# Generado por scripts/wazuh.mjs (no editar a mano).
services:
  wazuh.manager:
    volumes:
      - "${fwd(join(GENERATED, 'wazuh_manager.conf'))}:/wazuh-config-mount/etc/ossec.conf"
      - "${fwd(join(OPS, 'local_rules.xml'))}:/wazuh-config-mount/etc/rules/local_rules.xml"
      - "novatech_applogs:/var/log/novatech:ro"
      - "novatech_waflogs:/var/log/modsecurity:ro"

volumes:
  novatech_applogs:
    external: true
  novatech_waflogs:
    external: true
`
  writeFileSync(join(GENERATED, 'docker-compose.novatech.yml'), override)
  console.log(`  generado: ${fwd(join(GENERATED, 'wazuh_manager.conf'))}`)
  console.log(`  generado: ${fwd(join(GENERATED, 'docker-compose.novatech.yml'))}`)
  return true
}

function up() {
  console.log('\n1/5 Código oficial de Wazuh')
  if (!existsSync(STACK)) {
    run('git', ['clone', '--depth', '1', '--branch', VERSION, 'https://github.com/wazuh/wazuh-docker.git', UPSTREAM])
  } else console.log('  ya presente')

  console.log('\n2/5 Certificados TLS del indexador')
  if (!existsSync(join(STACK, 'config', 'wazuh_indexer_ssl_certs', 'root-ca.pem'))) {
    run('docker', ['compose', '-f', 'generate-indexer-certs.yml', 'run', '--rm', 'generator'], { cwd: STACK })
  } else console.log('  ya generados')

  console.log('\n3/5 Configuración de NovaTech para Wazuh')
  generate()

  console.log('\n4/5 Volúmenes de logs compartidos')
  for (const v of VOLUMES) run('docker', ['volume', 'create', v])

  console.log('\n5/5 Contenedores')
  run('docker', ['compose', ...composeFiles(), 'up', '-d'], { cwd: STACK })
  run('docker', ['compose', '-f', 'docker-compose.yml', '-f', 'docker-compose.siem.yml', 'up', '-d', '--build'])

  console.log(`
Listo. Wazuh tarda 1-2 minutos en inicializar.
  Panel:       https://localhost  (certificado autofirmado)
  Credenciales por defecto del laboratorio: admin / SecretPassword  (cámbialas fuera de un entorno local)
  Probar reglas: node scripts/wazuh.mjs logtest
Requisitos: ~6 GB de RAM libres para Docker. En Docker Desktop (WSL2), si el indexador no arranca:
  wsl -d docker-desktop sysctl -w vm.max_map_count=262144`)
}

function logtest() {
  const manager = capture('docker', ['compose', ...composeFiles(), 'ps', '-q', 'wazuh.manager'], STACK)
  if (!manager) {
    console.error('El manager de Wazuh no está en ejecución. Ejecuta primero: node scripts/wazuh.mjs up')
    process.exit(1)
  }
  console.log('Enviando ops/wazuh/samples.log a wazuh-logtest (busca "Rule id" y "level" en cada evento)...\n')
  run('docker', ['exec', '-i', manager, '/var/ossec/bin/wazuh-logtest'], { input: readFileSync(join(OPS, 'samples.log'), 'utf8') })
}

switch (command) {
  case 'up':
    up()
    break
  case 'logtest':
    logtest()
    break
  case 'status':
    run('docker', ['compose', ...composeFiles(), 'ps'], { cwd: STACK })
    break
  case 'down':
    run('docker', ['compose', ...composeFiles(), 'down', ...(flags.includes('--purge') ? ['-v'] : [])], { cwd: STACK })
    break
  default:
    console.log('Uso: node scripts/wazuh.mjs <up|logtest|status|down> [--dry-run] [--purge]')
}

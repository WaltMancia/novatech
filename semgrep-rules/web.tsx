// Casos de prueba de las reglas de .semgrep/web.yml (se ejecutan con `semgrep --test .semgrep`).

export function Unsafe({ html }: { html: string }) {
  // ruleid: novatech-web-dangerous-html
  return <div dangerouslySetInnerHTML={{ __html: html }} />
}

export function Safe({ text }: { text: string }) {
  // ok: novatech-web-dangerous-html
  return <div>{text}</div>
}

export function dom(el: HTMLElement, value: string) {
  // ruleid: novatech-web-dangerous-html
  el.innerHTML = value
  // ruleid: novatech-web-dangerous-html
  document.write(value)
  // ok: novatech-web-dangerous-html
  el.textContent = value
}

export function exec(code: string) {
  // ruleid: novatech-web-code-execution
  eval(code)
  // ruleid: novatech-web-code-execution
  return new Function(code)
}

export function storage(token: string) {
  // ruleid: novatech-web-token-in-storage
  localStorage.setItem('token', token)
  // ruleid: novatech-web-token-in-storage
  window.sessionStorage.setItem('token', token)
  // ok: novatech-web-token-in-storage
  const x = localStorage.getItem('theme')
  return x
}

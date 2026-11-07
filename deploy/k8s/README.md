# Despliegue en Kubernetes

Validado de forma estática (kubeconform y Checkov en el CI); **no se ha probado en un clúster real**.

## Contenido
`kustomization.yaml` agrupa: espacio de nombres (Pod Security `restricted`), cuenta de servicio sin token, ConfigMap, MariaDB (StatefulSet + PVC), API (php-fpm), web (Nginx), WAF (OWASP CRS), `NetworkPolicy` e Ingress. `job-migrate.yaml` ejecuta migraciones y semilla bajo demanda. Las exclusiones del WAF salen de `ops/waf/` (única fuente de verdad).

## Prueba local con kind (PowerShell)
Para que las `NetworkPolicy` se apliquen, el clúster necesita un CNI como Calico o Cilium (el CNI por defecto de kind no las aplica).

```powershell
docker build -t novatech-app:local -f docker/php/Dockerfile .
docker build -t novatech-web:local -f docker/nginx/Dockerfile .
kind create cluster --name novatech
kind load docker-image novatech-app:local novatech-web:local --name novatech

kubectl apply -f deploy/k8s/namespace.yaml

# Secretos aleatorios (no se guardan en el repositorio)
function New-Secret { $b = New-Object byte[] 24; [Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($b); [BitConverter]::ToString($b).Replace('-', '').ToLower() }
kubectl create secret generic novatech-secrets -n novatech --from-literal=DB_PASSWORD=$(New-Secret) --from-literal=DB_ROOT_PASSWORD=$(New-Secret)

kubectl kustomize deploy/k8s --load-restrictor LoadRestrictionsNone | kubectl apply -f -
kubectl -n novatech wait --for=condition=ready pod -l app.kubernetes.io/name=db --timeout=180s
kubectl apply -f deploy/k8s/job-migrate.yaml
kubectl -n novatech port-forward svc/waf 8080:8080     # http://localhost:8080
```

## Producción
- Sustituir las imágenes locales por un registro propio y fijarlas por *digest* (sección `images` de `kustomization.yaml`).
- Ajustar `APP_URL` en `configmap.yaml`, el host y la `ingressClassName` en `ingress.yaml`, y crear el secreto TLS `novatech-tls`.
- Etiquetar el namespace del Ingress Controller: `kubectl label namespace <ns> novatech.io/ingress-controller=true`.
- La API corre con 1 réplica (ver `docs/OPERATIONS.md`).

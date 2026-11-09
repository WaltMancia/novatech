# Cloudflare delante de NovaTech (guía)

Guía conceptual: no se ha desplegado un dominio real. Los nombres de las opciones pueden variar en el panel de Cloudflare.

## Qué aporta
- **TLS** de cara al usuario y mitigación **DDoS volumétrica** (riesgo R14, transferido al proveedor).
- **WAF gestionado** y **reglas de límite de tasa** como primera capa, antes de llegar al WAF propio (defensa en profundidad).
- Ocultar la IP real del servidor de origen.

## Configuración recomendada
1. **DNS:** registro del dominio con el proxy de Cloudflare activado (nube naranja).
2. **SSL/TLS:** modo *Full (strict)* con un certificado de origen de Cloudflare instalado en el Ingress o balanceador. Activar *Always Use HTTPS* y HSTS.
3. **WAF:** activar los conjuntos gestionados; empezar en modo registro y pasar a bloqueo tras revisar eventos.
4. **Límite de tasa:** regla sobre `/api/auth/*` (por ejemplo, 10 solicitudes por minuto por IP) que complementa el límite de la aplicación.
5. **Bots:** activar la protección básica de bots.
6. **Origen:** aceptar tráfico solo desde los rangos de IP de Cloudflare (lista publicada por Cloudflare) o usar *Authenticated Origin Pulls*.

## IP real del cliente
Cloudflare envía la IP original en `CF-Connecting-IP`. Para que el límite de tasa, el RASP y los logs vean al cliente y no a Cloudflare, configurar el WAF de NovaTech (variables de la imagen CRS):

```yaml
environment:
  REAL_IP_HEADER: CF-Connecting-IP
  SET_REAL_IP_FROM: "<rangos IPv4 e IPv6 publicados por Cloudflare>"
```

Mantener actualizada la lista de rangos y no confiar en esa cabecera si el origen es accesible directamente desde Internet (por eso se restringe el origen al paso anterior).

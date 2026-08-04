#!/usr/bin/env bash
# Restringe el trafico que Docker reenvia a los contenedores publicados (80/443)
# a los rangos de IP de Cloudflare. Sin esto, ufw no filtra nada de lo que
# Docker publica con -p, porque ese trafico pasa por la cadena DOCKER-USER
# (FORWARD), no por INPUT, que es donde actua ufw.
#
# Rangos oficiales: https://www.cloudflare.com/ips/ (revisar de tanto en tanto)
set -euo pipefail

if [[ $EUID -ne 0 ]]; then
  echo "Correr con sudo." >&2
  exit 1
fi

CF_V4=(
  173.245.48.0/20 103.21.244.0/22 103.22.200.0/22 103.31.4.0/22
  141.101.64.0/18 108.162.192.0/18 190.93.240.0/20 188.114.96.0/20
  197.234.240.0/22 198.41.128.0/17 162.158.0.0/15 104.16.0.0/13
  104.24.0.0/14 172.64.0.0/13 131.0.72.0/22
)
CF_V6=(
  2400:cb00::/32 2606:4700::/32 2803:f800::/32 2405:b500::/32
  2405:8100::/32 2a06:98c0::/29 2c0f:f248::/32
)

# Subred de la red de Docker Compose del proyecto (docker network inspect
# glowstudiov2_default). Todas las reglas de abajo se acotan con -d a esta
# subred: son sobre trafico REENVIADO HACIA los contenedores (publicado con
# -p en compose), nunca sobre trafico saliente DE los contenedores hacia
# internet (ej. npm/composer/fonts durante un build o request runtime) --
# ese trafico tiene como destino una IP externa, no esta subred, y nunca
# matchea estas reglas.
DOCKER_NET_V4="172.18.0.0/16"

echo "Limpiando reglas previas en DOCKER-USER (idempotente)..."
iptables -F DOCKER-USER
ip6tables -F DOCKER-USER

echo "1) Conexiones ya establecidas: siempre permitidas"
iptables -A DOCKER-USER -m state --state RELATED,ESTABLISHED -j RETURN
ip6tables -A DOCKER-USER -m state --state RELATED,ESTABLISHED -j RETURN

echo "2) Solo Cloudflare puede llegar a los contenedores en 80/443"
for net in "${CF_V4[@]}"; do
  iptables -A DOCKER-USER -s "$net" -d "$DOCKER_NET_V4" -p tcp -m multiport --dports 80,443 -j RETURN
done
for net in "${CF_V6[@]}"; do
  ip6tables -A DOCKER-USER -s "$net" -p tcp -m multiport --dports 80,443 -j RETURN
done

echo "3) Cualquier otra IP que intente llegar a los contenedores en 80/443: descartar"
iptables -A DOCKER-USER -d "$DOCKER_NET_V4" -p tcp -m multiport --dports 80,443 -j DROP
# No hay red IPv6 de Docker publicada (ver 'docker network inspect' -- solo
# subred v4), asi que no hay -d que acotar aca; si en el futuro se habilita
# IPv6 en la red de Docker, agregar el mismo filtro -d con esa subred.

echo "4) Todo lo demas sigue su curso normal"
iptables -A DOCKER-USER -j RETURN
ip6tables -A DOCKER-USER -j RETURN

echo ""
echo "Reglas aplicadas. Estado de DOCKER-USER:"
iptables -L DOCKER-USER -n -v

echo ""
echo "Instalando iptables-persistent para que sobreviva a un reboot..."
DEBIAN_FRONTEND=noninteractive apt-get install -y iptables-persistent
netfilter-persistent save

echo ""
echo "Listo. Probar desde OTRA maquina (no desde este servidor):"
echo "  curl -m 5 -k https://204.168.190.217/up      -> deberia fallar/timeout"
echo "  curl -m 5 https://citas.glowstudios.vip/up   -> deberia seguir dando 200"

#!/usr/bin/env bash
# Reemplazo de ufw (removido por conflicto con iptables-persistent) para
# filtrar trafico dirigido al HOST (cadena INPUT). Complementa a
# harden-docker-firewall.sh, que ya filtra el trafico reenviado a los
# contenedores (cadena DOCKER-USER / FORWARD).
#
# Orden critico: primero se agregan las reglas ACCEPT, recien al final se
# cambia la politica default a DROP. Si se hiciera al reves, la sesion SSH
# actual se cortaria antes de llegar a la regla que la permite.
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

echo "Limpiando reglas previas en INPUT (idempotente)..."
iptables -F INPUT
ip6tables -F INPUT

echo "1) Loopback"
iptables -A INPUT -i lo -j ACCEPT
ip6tables -A INPUT -i lo -j ACCEPT

echo "2) Conexiones ya establecidas"
iptables -A INPUT -m state --state ESTABLISHED,RELATED -j ACCEPT
ip6tables -A INPUT -m state --state ESTABLISHED,RELATED -j ACCEPT

echo "3) SSH (22/tcp) abierto a todos, como antes"
iptables -A INPUT -p tcp --dport 22 -j ACCEPT
ip6tables -A INPUT -p tcp --dport 22 -j ACCEPT

echo "4) 80/443 solo desde Cloudflare (por si Docker escucha directo en el host)"
for net in "${CF_V4[@]}"; do
  iptables -A INPUT -s "$net" -p tcp -m multiport --dports 80,443 -j ACCEPT
done
for net in "${CF_V6[@]}"; do
  ip6tables -A INPUT -s "$net" -p tcp -m multiport --dports 80,443 -j ACCEPT
done

echo "5) Politica default DROP (se aplica al final, a proposito)"
iptables -P INPUT DROP
ip6tables -P INPUT DROP
iptables -P FORWARD DROP
ip6tables -P FORWARD DROP
iptables -P OUTPUT ACCEPT
ip6tables -P OUTPUT ACCEPT

echo ""
echo "Estado final de INPUT:"
iptables -L INPUT -n -v --line-numbers

echo ""
echo "Guardando ruleset (sobrescribe el guardado inseguro anterior)..."
netfilter-persistent save

echo ""
echo "Listo. Verificar YA, sin cerrar esta sesion SSH:"
echo "  Desde otra terminal: ssh dev@204.168.190.217   -> debe conectar bien"
echo "  Desde otra maquina:  curl -m5 https://citas.glowstudios.vip/up -> 200"

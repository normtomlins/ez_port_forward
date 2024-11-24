iptables --flush
iptables -t nat --flush
iptables --delete-chain
ifreload --all

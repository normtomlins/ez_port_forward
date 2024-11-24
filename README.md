# ez_port_forward
This will create rules in Proxmox to allow nat forwarding, this project is based on work from

https://github.com/rk-exxec/ez_port_forward

Why did I write this in PHP?   I just don't like using Python, and I wanted to add some extra
features like rtp traffic forwarding and creating port forwarding for UDP in a range.  

You can install using:
         apt install php php-yaml

Sample port_conf.yaml file.

vmbr0:
  bridge: eno1
  subnet: 192.168.1.0/24
  forwards:
    101:
      udp:
        "10000:12000": "10000:12000"
      ssh: true
      http: true
    102:
      tcp:
        "321": "123"
        "345": "345"
        "765": "567"
      rtp: true
    103:
      tcp:
      rtp: true
    201:
      ssh: true
      http: true
      rtp: true
    202:
      tcp:
        "20222": "22"
    233:
      ssh: 23
vmbr1:
  bridge: eno1
  subnet: 192.168.0.0/16
  forwards:
    400:
      tcp: "234,567"
      

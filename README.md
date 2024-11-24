# ez_port_forward
This will create rules in Proxmox to allow nat forwarding, this project is based on work from

https://github.com/rk-exxec/ez_port_forward

Why did I write this in PHP?   I just don't like using Python, and I wanted to add some extra
features like rtp traffic forwarding and creating port forwarding for UDP in a range.  And
wanted to have it auto create http forwarding as well.

You can install using:
         apt install php php-yaml

Sample port_conf.yaml file.

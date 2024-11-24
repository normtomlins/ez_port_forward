<?php

function parse_yaml($yaml_file) {
    $content = file_get_contents($yaml_file);
    if ($content === false) {
        throw new Exception("Unable to read the YAML file: $yaml_file");
    }
    $data = yaml_parse($content);
    if ($data === false) {
        throw new Exception("Error parsing YAML file: $yaml_file");
    }
    return $data;
}

function parse_ports($ports) {
    if (is_int($ports)) {
        // Single port as integer
        return [$ports => $ports];
    }
    if (is_string($ports)) {
        if (strpos($ports, ',') !== false) {
            // Comma-separated list of ports
            $port_list = explode(',', $ports);
            $result = [];
            foreach ($port_list as $port) {
                $port = trim($port);
                $result[$port] = $port;
            }
            return $result;
        }
        // Single port as string
        return [$ports => $ports];
    }
    if (is_array($ports)) {
        // Already in the correct format
        return $ports;
    }
    return null; // If the input is invalid, return null
}


function parse_ssh_rule($ssh_rule, $container_id) {
    if ($ssh_rule === true) {
        // Default SSH port: container_id * 100 + 22
        return [(int)($container_id * 100 + 22) => 22];
    }
    if (is_int($ssh_rule)) {
        return [(int)($container_id * 100 + $ssh_rule) => $ssh_rule];
    }
    return null;
}

function parse_http_rule($http_rule, $container_id) {
    if ($http_rule === true) {
        // Default HTTP port: container_id * 100 + 80
        return [(int)(8000 + $container_id ) => 80];
    }
    if (is_int($http_rule)) {
        return [(int)($container_id * 100 + $http_rule) => $http_rule];
    }
    return null;
}

function parse_rtp_rule($rtp_rule, $container_id) {
    if ($rtp_rule === true) {
        // Correct range calculation for RTP
        $start_port = 10000 + ($container_id * 100);
        $end_port = $start_port + 99;
        return ["$start_port:$end_port" => "$start_port:$end_port"];
    }
    return null;
}

function build_command($protocol, $bridge, $target_ip, $source_range, $target_range = null) {
    if (!$target_range) $target_range = $source_range;

    // Replace ":" with "-" in the destination range for iptables
    $target_range = str_replace(':', '-', $target_range);

    return "        post-up iptables -t nat -A PREROUTING -i $bridge -p $protocol --dport $source_range -j DNAT --to $target_ip:$target_range\n";
}

function write_container_commands($file, $ip, $bridge, $ssh_rule = null, $http_rule = null, $rtp_rule = null, $tcp_rules = null, $udp_rules = null, $tcpudp_rules = null) {
    $existing_port_maps = [];

    $write_helper = function ($prot, $src, $dest) use ($file, $ip, $bridge, &$existing_port_maps) {
        if (!$src || !$dest) {
            error_log("Either Port $src or $dest is invalid.");
            return;
        }

        if (strpos($src, ':') !== false || strpos($dest, ':') !== false) {
            // Handle port ranges
            fwrite($file, build_command($prot, $bridge, $ip, $src, $dest));
        } else {
            // Handle single ports
            if ($src > 65535 || $dest > 65535) {
                fwrite($file, "#");
            }
            if (isset($existing_port_maps["$src:$prot"])) {
                fwrite($file, "#");
            } else {
                $existing_port_maps["$src:$prot"] = $ip;
            }
            fwrite($file, build_command($prot, $bridge, $ip, $src, $dest));
        }
    };

    // Check and handle SSH rules
    if ($ssh_rule) {
        foreach ($ssh_rule as $src => $dest) {
            $write_helper('tcp', $src, $dest);
        }
    }

    // Check and handle HTTP rules
    if ($http_rule) {
        foreach ($http_rule as $src => $dest) {
            $write_helper('tcp', $src, $dest);
        }
    }

    // Check and handle RTP rules
    if ($rtp_rule) {
        foreach ($rtp_rule as $src => $dest) {
            $write_helper('udp', $src, $dest);
        }
    }

    // Check and handle TCP rules
    if (is_array($tcp_rules)) {
        foreach ($tcp_rules as $src => $dest) {
            $write_helper('tcp', $src, $dest);
        }
    }

    // Check and handle UDP rules
    if (is_array($udp_rules)) {
        foreach ($udp_rules as $src => $dest) {
            $write_helper('udp', $src, $dest);
        }
    }

    // Check and handle TCP/UDP rules
    if (is_array($tcpudp_rules)) {
        foreach ($tcpudp_rules as $src => $dest) {
            $write_helper('tcp', $src, $dest);
            $write_helper('udp', $src, $dest);
        }
    }
}

function write_iptables_file($yaml_dict, $out_file) {
    $file = fopen($out_file, 'w');
    foreach ($yaml_dict as $iname => $iconf) {
        fwrite($file, "#===========================\n");
        fwrite($file, "iface $iname inet static\n");
        $bridge = $iconf['bridge'];
        $subnet = $iconf['subnet'];

        foreach ($iconf['forwards'] as $cont_id => $cont_conf) {
            fwrite($file, "#--- Container $cont_id\n");
            $container_ip = long2ip(ip2long(explode('/', $subnet)[0]) + $cont_id);

            $ssh = parse_ssh_rule($cont_conf['ssh'] ?? null, $cont_id);
            $http = parse_http_rule($cont_conf['http'] ?? null, $cont_id);
            $rtp = parse_rtp_rule($cont_conf['rtp'] ?? null, $cont_id);
            $tcp = parse_ports($cont_conf['tcp'] ?? null);
            $udp = parse_ports($cont_conf['udp'] ?? null);
	    $tcpudp = parse_ports($cont_conf['tcpudp'] ?? $cont_conf['udptcp'] ?? null);

            write_container_commands($file, $container_ip, $bridge, $ssh, $http, $rtp, $tcp, $udp, $tcpudp);
        }
        fwrite($file, "#===========================\n");
    }
    fclose($file);
}

function main() {
    global $argv;
    $yaml_file = $argv[1] ?? './port_conf.yaml';
    $out_file = $argv[2] ?? '/etc/network/interfaces.d/port_forwards';

    if (!file_exists($yaml_file)) {
        die("Input file does not exist: $yaml_file\n");
    }

    $yaml_dict = parse_yaml($yaml_file);
    write_iptables_file($yaml_dict, $out_file);
}

main();

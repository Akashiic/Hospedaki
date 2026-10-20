<?php

namespace App\Support;

/**
 * Consulta de DNS (separada para os testes trocarem por respostas prontas).
 */
class Dns
{
    /**
     * Endereços IPv4 (registro A) do nome.
     *
     * @return list<string>
     */
    public function a(string $host): array
    {
        return array_values(array_filter(array_column(@dns_get_record($host, DNS_A) ?: [], 'ip')));
    }

    /**
     * Para onde o nome aponta (registro CNAME), sem o ponto final.
     *
     * @return list<string>
     */
    public function cname(string $host): array
    {
        return array_values(array_map(fn ($t) => rtrim(strtolower($t), '.'), array_filter(array_column(@dns_get_record($host, DNS_CNAME) ?: [], 'target'))));
    }
}

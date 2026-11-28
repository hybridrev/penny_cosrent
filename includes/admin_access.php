<?php
function rentalAdminPrivateIpv4Allowed(string $address): bool
{
    if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
        return false;
    }

    $octets = array_map("intval", explode(".", $address));
    $first = $octets[0];
    $second = $octets[1];

    return $first === 10
        || $first === 127
        || ($first === 172 && $second >= 16 && $second <= 31)
        || ($first === 192 && $second === 168)
        || ($first === 169 && $second === 254);
}

function rentalAdminClientAllowed(): bool
{
    $address = $_SERVER["REMOTE_ADDR"] ?? "";
    if (!is_string($address)) {
        return false;
    }

    if (rentalAdminPrivateIpv4Allowed($address) || $address === "::1") {
        return true;
    }

    if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
        return false;
    }

    $packedAddress = inet_pton($address);
    if ($packedAddress === false) {
        return false;
    }

    $ipv4MappedPrefix = str_repeat("\0", 10) . "\xff\xff";
    if (substr($packedAddress, 0, 12) === $ipv4MappedPrefix) {
        $mappedIpv4 = inet_ntop(substr($packedAddress, 12));
        return $mappedIpv4 !== false && rentalAdminPrivateIpv4Allowed($mappedIpv4);
    }

    $firstByte = ord($packedAddress[0]);
    $secondByte = ord($packedAddress[1]);

    return ($firstByte & 0xFE) === 0xFC
        || ($firstByte === 0xFE && ($secondByte & 0xC0) === 0x80);
}

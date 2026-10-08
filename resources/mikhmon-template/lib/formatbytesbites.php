<?php
// function format bytes
if (!function_exists('formatBytes')) {
    function formatBytes($size, $decimals = 0){
        $unit = array(
            '0' => 'Byte',
            '1' => 'KiB',
            '2' => 'MiB',
            '3' => 'GiB',
            '4' => 'TiB',
            '5' => 'PiB',
            '6' => 'EiB',
            '7' => 'ZiB',
            '8' => 'YiB'
        );

        if (!is_numeric($size) || empty($size) || (float)$size <= 0) {
            return '0 ' . $unit[0];
        }

        $size = (float)$size;
        $maxUnit = count($unit) - 1;
        $i = 0;
        while ($size >= 1024 && $i < $maxUnit) {
            $size = $size / 1024;
            $i++;
        }

        return round($size, (int)$decimals).' '.$unit[$i];
    }
}

// function format bytes2
if (!function_exists('formatBytes2')) {
    function formatBytes2($size, $decimals = 0){
        $unit = array(
            '0' => 'Byte',
            '1' => 'KB',
            '2' => 'MB',
            '3' => 'GB',
            '4' => 'TB',
            '5' => 'PB',
            '6' => 'EB',
            '7' => 'ZB',
            '8' => 'YB'
        );

        if (!is_numeric($size) || empty($size) || (float)$size <= 0) {
            return '0' . $unit[0];
        }

        $size = (float)$size;
        $maxUnit = count($unit) - 1;
        $i = 0;
        while ($size >= 1000 && $i < $maxUnit) {
            $size = $size / 1000;
            $i++;
        }

        return round($size, (int)$decimals).''.$unit[$i];
    }
}

// function format bites
if (!function_exists('formatBites')) {
    function formatBites($size, $decimals = 0){
        $unit = array(
            '0' => 'bps',
            '1' => 'kbps',
            '2' => 'Mbps',
            '3' => 'Gbps',
            '4' => 'Tbps',
            '5' => 'Pbps',
            '6' => 'Ebps',
            '7' => 'Zbps',
            '8' => 'Ybps'
        );

        if (!is_numeric($size) || empty($size) || (float)$size <= 0) {
            return '0 ' . $unit[0];
        }

        $size = (float)$size;
        $maxUnit = count($unit) - 1;
        $i = 0;
        while ($size >= 1000 && $i < $maxUnit) {
            $size = $size / 1000;
            $i++;
        }

        return round($size, (int)$decimals).' '.$unit[$i];
    }
}
?>
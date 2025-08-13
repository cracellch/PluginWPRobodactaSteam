<?php
/**
 * Hashids – Standalone PHP implementation (MIT)
 * Fuente: https://github.com/ivanakimov/hashids.php (versión legacy)
 */
class Hashids {
    public $version = '1.0.0';
    private $alphabet = '';
    private $salt = '';
    private $min_hash_length = 0;
    private $seps = '';
    private $guards = '';
    private $alphabet_original = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
    private $seps_default = 'cfhistuCFHISTU';

    public function __construct($salt = '', $min_hash_length = 0, $alphabet = '') {
        $this->salt = $salt;
        $this->min_hash_length = max(0, (int)$min_hash_length);
        $this->alphabet = $alphabet ? implode('', array_unique(str_split($alphabet))) : $this->alphabet_original;

        if (strlen($this->alphabet) < 16) throw new Exception('alphabet must contain at least 16 unique characters');
        if (strpos($this->alphabet, ' ') !== false) throw new Exception('alphabet cannot contain spaces');

        $this->seps = implode('', array_intersect(str_split($this->seps_default), str_split($this->alphabet)));
        $this->alphabet = str_replace(str_split($this->seps), '', $this->alphabet);

        $this->seps = $this->consistent_shuffle($this->seps, $this->salt);

        if (!$this->seps || (strlen($this->alphabet) / strlen($this->seps)) > 3.5) {
            $seps_len = (int)ceil(strlen($this->alphabet) / 3.5);
            if ($seps_len == 0) $seps_len = 1;
            if ($seps_len > strlen($this->seps)) {
                $diff = $seps_len - strlen($this->seps);
                $this->seps .= substr($this->alphabet, 0, $diff);
                $this->alphabet = substr($this->alphabet, $diff);
            }
        }
        $this->alphabet = $this->consistent_shuffle($this->alphabet, $this->salt);
        $guard_count = (int)ceil(strlen($this->alphabet) / 12);
        if (strlen($this->alphabet) < 3) {
            $this->guards = substr($this->seps, 0, $guard_count);
            $this->seps = substr($this->seps, $guard_count);
        } else {
            $this->guards = substr($this->alphabet, 0, $guard_count);
            $this->alphabet = substr($this->alphabet, $guard_count);
        }
    }

    public function encode() {
        $numbers = func_get_args();
        if (isset($numbers[0]) && is_array($numbers[0])) $numbers = $numbers[0];
        if (!count($numbers)) return '';
        foreach ($numbers as $number) if (!is_int($number) || $number < 0) return '';
        return $this->encode_numbers($numbers);
    }

    public function decode($hash) {
        $ret = [];
        if (!is_string($hash) || !($hash_length = strlen($hash))) return $ret;
        $hash_breakdown = str_replace(str_split($this->guards), ' ', $hash);
        $hash_array = explode(' ', $hash_breakdown);
        $i = 0;
        if (count($hash_array) == 3 || count($hash_array) == 2) $i = 1;
        $lottery = substr($hash_array[$i], 0, 1);
        $sub_hash = substr($hash_array[$i], 1);
        $sub_hash_breakdown = str_replace(str_split($this->seps), ' ', $sub_hash);
        $sub_hash_array = explode(' ', $sub_hash_breakdown);
        $alphabet = $this->alphabet;
        foreach ($sub_hash_array as $sub_hash) {
            $buffer = $lottery . $this->salt . $alphabet;
            $alphabet = $this->consistent_shuffle($alphabet, substr($buffer, 0, strlen($alphabet)));
            $ret[] = $this->unhash($sub_hash, $alphabet);
        }
        if ($this->encode($ret) != $hash) return [];
        return $ret;
    }

    // Métodos internos idénticos a los de la versión oficial
    private function encode_numbers($numbers) {
        $alphabet = $this->alphabet;
        $numbers_size = count($numbers);
        $numbers_hash_int = 0;
        foreach ($numbers as $i => $number) $numbers_hash_int += ($number % ($i + 100));
        $lottery = $ret = $alphabet[$numbers_hash_int % strlen($alphabet)];
        foreach ($numbers as $i => $number) {
            $buffer = $lottery . $this->salt . $alphabet;
            $alphabet = $this->consistent_shuffle($alphabet, substr($buffer, 0, strlen($alphabet)));
            $ret .= $this->hash($number, $alphabet);
            if ($i + 1 < $numbers_size) {
                $number %= (ord($ret[$i]) + $i);
                $ret .= $this->seps[$number % strlen($this->seps)];
            }
        }
        if (strlen($ret) < $this->min_hash_length) {
            $guard_index = ($numbers_hash_int + ord($ret[0])) % strlen($this->guards);
            $guard = $this->guards[$guard_index];
            $ret = $guard . $ret;
            if (strlen($ret) < $this->min_hash_length) {
                $guard_index = ($numbers_hash_int + ord($ret[2])) % strlen($this->guards);
                $guard = $this->guards[$guard_index];
                $ret .= $guard;
            }
        }
        $half_length = (int)(strlen($alphabet) / 2);
        while (strlen($ret) < $this->min_hash_length) {
            $alphabet = $this->consistent_shuffle($alphabet, $alphabet);
            $ret = substr($alphabet, $half_length) . $ret . substr($alphabet, 0, $half_length);
            $excess = strlen($ret) - $this->min_hash_length;
            if ($excess > 0) $ret = substr($ret, $excess / 2, $this->min_hash_length);
        }
        return $ret;
    }

    private function consistent_shuffle($alphabet, $salt) {
        if (!strlen($salt)) return $alphabet;
        $chars = str_split($alphabet);
        $salt_chars = str_split($salt);
        $len = strlen($alphabet);
        $v = 0; $p = 0;
        for ($i = $len - 1; $i > 0; $i--, $v++) {
            $v %= strlen($salt);
            $p += $int = ord($salt_chars[$v]);
            $j = ($int + $v + $p) % $i;
            $tmp = $chars[$j];
            $chars[$j] = $chars[$i];
            $chars[$i] = $tmp;
        }
        return implode('', $chars);
    }

    private function hash($input, $alphabet) {
        $hash = '';
        $alphabet_length = strlen($alphabet);
        do {
            $hash = $alphabet[$input % $alphabet_length] . $hash;
            $input = (int)($input / $alphabet_length);
        } while ($input);
        return $hash;
    }

    private function unhash($input, $alphabet) {
        $number = 0;
        if (strlen($input)) {
            $alphabet_length = strlen($alphabet);
            foreach (str_split($input) as $i => $char) {
                $pos = strpos($alphabet, $char);
                if ($pos === false) return 0;
                $number = $number * $alphabet_length + $pos;
            }
        }
        return $number;
    }
}

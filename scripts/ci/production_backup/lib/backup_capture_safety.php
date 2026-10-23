<?php
/** Pure bounded filesystem helpers; no database or network access. */
final class OrgaVelaInventoryRejection extends RuntimeException {
    private ?string $privateRelativeFile;
    public function __construct(string $code, ?string $privateRelativeFile=null) {
        parent::__construct($code);
        $this->privateRelativeFile=$privateRelativeFile;
    }
    public function privateRelativeFile(): ?string { return $this->privateRelativeFile; }
}
/** One source of truth: the same exact omission for probe and production TAR. */
function orgavela_capture_tar_args(string $root): array {
    return ['tar','--create','--gzip','--file','-',
        '--exclude=./.maintenance',
        '--directory',$root,'.'];
}

function orgavela_capture_inventory(string $root): array {
    $result = [];
    $total = 0;
    $walk = static function(string $dir) use (&$walk, &$result, &$total, $root): void {
        foreach (new DirectoryIterator($dir) as $entry) {
            if ($entry->isDot()) continue;
            $path = $entry->getPathname();
            $relative = substr($path, strlen($root) + 1);
            if ($relative === '.maintenance') continue;
            // Exact original fail-closed checks; only fixed non-identifying error categories differ.
            if (str_contains($relative, "\n") || str_contains($relative, "\r") || str_contains($relative, "\\")) throw new OrgaVelaInventoryRejection('inventory_invalid_name');
            if (count($result) >= 50000) throw new OrgaVelaInventoryRejection('inventory_entry_limit');
            if ($entry->isLink()) throw new OrgaVelaInventoryRejection('inventory_symlink');
            if ($entry->isDir()) { $result[$relative . '/'] = 'directory'; $walk($path); }
            elseif ($entry->isFile()) {
                $total += $entry->getSize();
                if ($total > 1073741824) throw new OrgaVelaInventoryRejection('inventory_total_size');
                $hash = hash_file('sha256', $path);
                if ($hash === false) throw new OrgaVelaInventoryRejection('inventory_unreadable_file',$relative);
                $result[$relative] = $hash;
            } else throw new OrgaVelaInventoryRejection('inventory_unsupported_entry');
        }
    };
    $walk($root); ksort($result, SORT_STRING);
    return $result;
}

function orgavela_remove_owned_maintenance(string $path, string $hash): bool {
    if (!file_exists($path)) return true;
    if (is_link($path) || !hash_equals($hash, (string)hash_file('sha256', $path))) return false;
    return unlink($path);
}

function orgavela_client_value(string $value): string {
    if (str_contains($value, "\0")) throw new RuntimeException('invalid client value');
    return '"' . strtr($value, ['\\'=>'\\\\', '"'=>'\\"', "\n"=>'\\n', "\r"=>'\\r', "\t"=>'\\t']) . '"';
}

function orgavela_tar_header(string $name, int $size): string {
    if (!in_array($name,['database.sql.gz','wordpress-files.tar.gz','capture-evidence.json'],true) || $size<0 || $size>134217728) throw new RuntimeException('invalid tar member');
    $header=str_pad($name,100,"\0").sprintf('%07o',0600)."\0".sprintf('%07o',0)."\0".sprintf('%07o',0)."\0".
        sprintf('%011o',$size)."\0".sprintf('%011o',time())."\0".str_repeat(' ',8).'0'.str_repeat("\0",100).
        "ustar\00000".str_repeat("\0",32+32+8+8+155+12);
    if (strlen($header)!==512) throw new RuntimeException('invalid tar header');
    $sum=array_sum(unpack('C*',$header));
    return substr_replace($header,sprintf('%06o',$sum)."\0 ",148,8);
}

function orgavela_lock_capture_tables(mysqli $db, array $tables): bool {
    // InnoDB table locks require autocommit=0 and innodb_table_locks=1.
    if (!$db->query('SET SESSION lock_wait_timeout=2, innodb_lock_wait_timeout=2') || !$db->autocommit(false)) return false;
    $r=$db->query('SELECT @@innodb_table_locks');
    if (!$r || (int)$r->fetch_row()[0]!==1) return false;
    return (bool)$db->query('LOCK TABLES '.implode(',',array_map(static fn($t)=>'`'.$t.'` READ',$tables)).' WAIT 2');
}

function orgavela_dump_client_args(string $help, bool $schema): array {
    if (!str_contains($help,'--defaults-file')) throw new RuntimeException('controlled defaults unsupported');
    $args=['mariadb-dump','--defaults-file=/proc/self/fd/3','--single-transaction','--skip-lock-tables'];
    if (str_contains($help,'--max-statement-time')) $args[]='--max-statement-time='.($schema?'2':'15');
    return array_merge($args,$schema ? ['--skip-triggers','--no-data'] : ['--quick','--hex-blob','--default-character-set=utf8mb4','--skip-comments']);
}

function orgavela_capture_parse_status(array $headers): int {
    foreach($headers as $line)if(is_string($line)&&preg_match('~^HTTP/\\S+\\s+(\\d{3})\\b~',$line,$m))return(int)$m[1];
    throw new RuntimeException('origin status unavailable');
}

function orgavela_capture_origin_status(): int {
    $context=stream_context_create(['http'=>['timeout'=>5,'ignore_errors'=>true,'follow_location'=>0,
        'header'=>"Cache-Control: no-cache\r\nPragma: no-cache\r\n"]]);
    $headers=@get_headers('https://orgavela.com/?rest_route=/&orgavela_backup_probe='.bin2hex(random_bytes(12)),false,$context);
    if(!is_array($headers))throw new RuntimeException('origin status unavailable');
    return orgavela_capture_parse_status($headers);
}


function orgavela_validate_tables(array $tables, string $prefix): bool {
    if (count($tables)!==52 || count(array_unique($tables))!==52 || !preg_match('/^[A-Za-z0-9_]{1,64}$/D',$prefix)) return false;
    foreach ($tables as $table) if (!is_string($table) || !preg_match('/^[A-Za-z0-9_]+$/D',$table) || !str_starts_with($table,$prefix)) return false;
    foreach (['options','posts','users','usermeta','comments','uop_organizations','uop_persons','uop_consent_records','uop_registrations','uop_waitlist_entries'] as $name)
        if (!in_array($prefix.$name,$tables,true)) return false;
    return true;
}

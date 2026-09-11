<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

function ejecutarRespaldoCoordinadoSintetico(string $raiz, array $entorno = []): Process
{
    $bin = $raiz.'/bin';
    mkdir($bin, 0700, true);
    $docker = <<<'SH'
#!/bin/sh
printf '%s\n' "$*" >>"$QA_DOCKER_LOG"
case " $* " in
  *" run "*)
    [ "${QA_FAIL_BACKUP:-0}" = 1 ] && exit 42
    for argumento in "$@"; do
      case "$argumento" in
        *:/evidencia)
          destino=${argumento%:/evidencia}
          printf '%s\n' '{"version_formato":1,"estado":"COMPLETO"}' >"$destino/manifiesto-documentos.json"
          ;;
      esac
    done
    ;;
  *" pg_dump "*) printf '%s' 'dump-sintetico' ;;
  *" psql "*) printf '%s\n' '0' ;;
  *" start "*) [ "${QA_FAIL_START:-0}" = 1 ] && exit 43 ;;
esac
exit 0
SH;
    file_put_contents($bin.'/docker', $docker);
    chmod($bin.'/docker', 0700);

    $process = new Process(
        ['/bin/sh', base_path('scripts/depositos/respaldo-coordinado.sh')],
        base_path(),
        array_merge([
            'PATH' => $bin.':'.getenv('PATH'),
            'COMPOSE_FILE' => $raiz.'/compose.yml',
            'COMPOSE_PROJECT' => 'qa-respaldo',
            'BACKUP_ROOT' => $raiz.'/respaldos',
            'BACKUP_ID' => 'qa-script',
            'BACKUP_PREFIX' => 'respaldos-depositos/qa-script',
            'QA_DOCKER_LOG' => $raiz.'/docker.log',
        ], $entorno),
    );
    $process->run();

    return $process;
}

function eliminarArbolRespaldoSintetico(string $ruta): void
{
    if (! is_dir($ruta)) {
        return;
    }
    $iterador = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($ruta, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterador as $elemento) {
        $elemento->isDir() ? rmdir($elemento->getPathname()) : unlink($elemento->getPathname());
    }
    rmdir($ruta);
}

test('el respaldo coordinado preserva copias completas y reporta la recuperacion de servicios', function (): void {
    $raiz = sys_get_temp_dir().'/hubdigital-respaldo-script-'.bin2hex(random_bytes(6));

    try {
        $destino = $raiz.'/respaldos/qa-script';
        mkdir($destino, 0700, true);
        $completo = '{"version_formato":1,"id":"qa-script","estado":"COMPLETO"}'.PHP_EOL;
        file_put_contents($destino.'/manifiesto-coordinado.json', $completo);
        $huella = hash_file('sha256', $destino.'/manifiesto-coordinado.json');

        $repetido = ejecutarRespaldoCoordinadoSintetico($raiz);
        expect($repetido->getExitCode())->toBe(4)
            ->and($repetido->getErrorOutput())->toContain('ya esta COMPLETO')
            ->and(hash_file('sha256', $destino.'/manifiesto-coordinado.json'))->toBe($huella);

        unlink($destino.'/manifiesto-coordinado.json');
        $falloRecuperable = ejecutarRespaldoCoordinadoSintetico($raiz, ['QA_FAIL_BACKUP' => '1']);
        $incompleto = json_decode((string) file_get_contents($destino.'/manifiesto-coordinado.json'), true, 512, JSON_THROW_ON_ERROR);
        $log = (string) file_get_contents($raiz.'/docker.log');
        expect($falloRecuperable->getExitCode())->toBe(42)
            ->and($incompleto['estado'])->toBe('INCOMPLETO')
            ->and($log)->toContain('start app worker scheduler nginx')
            ->and($log)->toContain('artisan up');

        unlink($destino.'/manifiesto-coordinado.json');
        file_put_contents($raiz.'/docker.log', '');
        $falloRecuperacion = ejecutarRespaldoCoordinadoSintetico($raiz, [
            'QA_FAIL_BACKUP' => '1',
            'QA_FAIL_START' => '1',
        ]);
        $incompleto = json_decode((string) file_get_contents($destino.'/manifiesto-coordinado.json'), true, 512, JSON_THROW_ON_ERROR);
        expect($falloRecuperacion->getExitCode())->toBe(5)
            ->and($falloRecuperacion->getErrorOutput())->toContain('recuperacion manual')
            ->and($incompleto['recuperacion_servicios_fallida'])->toBe(1);
    } finally {
        eliminarArbolRespaldoSintetico($raiz);
    }
});

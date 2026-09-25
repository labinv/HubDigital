<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('recepciones.fuentes_revocacion_firma')) {
            return;
        }

        // Se retira solo el valor inicial; las excepciones administradas se conservan.
        DB::table('recepciones.fuentes_revocacion_firma')
            ->where('codigo', 'SECURITY_DATA')
            ->where('patron_emisor', 'SECURITY DATA')
            ->where('ocsp_url', 'http://ocspgw.securitydata.net.ec/ejbca/publicweb/status/ocsp')
            ->where('crl_url', 'https://portal-operador2.securitydata.net.ec/ejbca/publicweb/webdist/certdist?cmd=crl&issuer=CN%3DAUTORIDAD+DE+CERTIFICACION+SUBCA-2+SECURITY+DATA%2COU%3DENTIDAD+DE+CERTIFICACION+DE+INFORMACION%2CO%3DSECURITY+DATA+S.A.+2%2CC%3DEC')
            ->where('revocacion_obligatoria', true)
            ->where('horas_actualizacion', 8)
            ->delete();
    }

    public function down(): void
    {
        // No restablece una dirección de proveedor que no se haya comprobado.
    }
};

<?php
/* Doctrine Entities Creator for PostgreSQL

Need run with php-cli, and need 2 parameters to correct operation.

Two parameters are required. Call example:

php create_entities.php [table name] [Entities Filename Output] [Verbose result (true or false)]


*/

/* */
$host   = getenv('PG_HOST');
$dbuser = getenv('PG_USER');
$dbpass = getenv('PG_PASS');
$dbname = "emite_facil";

$tabela = @$argv[1];
$nomeArquivoController = @$argv[2];
$verbose = @$argv[3];

if(!isset($nomeArquivoController)) {
    print("\r\n\r\n");
    print("[ERRO] Sintaxe inválida. Utilize \"php cria_entitites.php nome_da_tabela nome_arguivo_controller_a_ser_gerado \".\r\n");
    print("\r\n\r\n");
    exit;
}

if( (!isset($verbose)) or ($verbose == "false")) {
    $verbose = false;
}


$conn = pg_connect("host=".$host." port=5432 dbname=".$dbname." user=".$dbuser." password=".$dbpass);
if(!$conn) {
    print("Connection error!\r\n");
    exit;
}


function createDefinitionFields($conn, $tableName, $arrCampos) {
    $linha  = "";
    foreach ($arrCampos as $chave => $campo) {
        if ($campo['type'] == 'bool') {
            $tipo = "boolean";
        } elseif ($campo['type'] == 'int4') {
            $tipo = "integer";
        } elseif ($campo['type'] == 'float8') {
            $tipo = "float";
        } else {
            $tipo = $campo['type'];
        }

        if ($campo['not null'] == 'true') {
            $nullable = "false";
        } else {
            $nullable = "true";
        }

        # verifica se campo é chave primária
        if(isPrimaryKey($conn, $tableName, $chave))
            $linha_primary_key = "@ORM\Id";
        else
            $linha_primary_key = "";
        $linha .= "    /** 
    *
    * @ORM\Column(name=\"" . $chave . "\", type=\"" . $tipo . "\", nullable=" . $nullable . ")
    * ".$linha_primary_key."
    *
    */ \r\n";
        $linha .= "    private $" . $chave . ";\r\n";
    }
    return $linha;
}

function isPrimaryKey($conn, $tabela, $nomeCampo){
    # verifica se campo é uma chave primária
    $sql = "SELECT               
          pg_attribute.attname, 
          format_type(pg_attribute.atttypid, pg_attribute.atttypmod) 
        FROM pg_index, pg_class, pg_attribute, pg_namespace 
        WHERE 
          pg_class.oid = '".$tabela ."'::regclass AND 
          indrelid = pg_class.oid AND 
          nspname = 'public' AND 
          pg_attribute.attname = '".$nomeCampo."' and 
          pg_class.relnamespace = pg_namespace.oid AND 
          pg_attribute.attrelid = pg_class.oid AND 
          pg_attribute.attnum = any(pg_index.indkey)
         AND indisprimary";

    $result = pg_query($conn, $sql);
    if(pg_num_rows($result)>0)
        return true;
    else
        return false;

}
function createSets($arrCampos) {
    $linha  = "";
    foreach ($arrCampos as $chave => $campo) {
        $linha .= "\r\n";
        $linha .= "    public function set".ucwords($chave)."(\$$chave)\r\n";
        $linha .= "        { \r\n";
        $linha .= "            \$this->$chave = \$$chave; \r\n ";
        $linha .= "       } \r\n";
    }
    return $linha;
}

function createGets($arrCampos) {
    $linha  = "";
    foreach ($arrCampos as $chave => $campo) {
        $linha .= "\r\n";
        $linha .= "    public function get".ucwords($chave)."()\r\n";
        $linha .= "        { \r\n";
        $linha .= "            return \$this->$chave; \r\n ";
        $linha .= "       } \r\n";
    }
    return $linha;
}

function insertHeaderFile($tableName) {
    $ret  = "<?php

namespace ModuloFinanceiro\Entities;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\EntityManagerInterface AS EM;
use Doctrine\Common\Persistence\ManagerRegistry; \r\n
\r\n

/* Gerado com troliveira/cria_entities 
https://github.com/tiagooliveira1/doctrine-create-entities-postgresql */

/**
 * ".ucwords($tableName)."
 *
 * @ORM\Table(name=\"".$tableName."\")
 * @ORM\Entity
 */

";

    return $ret;
}

function insereDefinicaoClasse($nomeClasse) {
    $ret  = "\r\n";
    $ret .= "class ".ucwords($nomeClasse)."\r\n";
    $ret .="{\r\n";

    return $ret;
}

function insertClassEnd() {
    return "}";
}
print("\r\nExtracting metadata...\r\n");

$metadata = pg_meta_data($conn, $tabela);


/* Define os campos */
$content  = insertHeaderFile($tabela);
$content .= insereDefinicaoClasse($tabela);
$content .= createDefinitionFields($conn, $tabela, $metadata);
$content .= createSets($metadata);
$content .= createGets($metadata);
$content .= insertClassEnd();

if($verbose == true)
    print("\r\n\r\n".$content."\r\n\r\n");

try {
    print("\r\nSaving output file ".$nomeArquivoController."...\r\n");
    $fp = fopen($nomeArquivoController, "w+");
    $write = fwrite($fp, $content);
    fclose($fp);
} catch (\Exception $e) {
    var_dump($e->getMessage());
}






<?php
/*
*  @@Criado por: Vitor Hugo Marini
*  Atualizado em : 27/11/2019
*  PROJETO FEITO PARA EXPORTAÇÃO DE ARQUIVO TXT DE DOCUMENTOS REMESSA PADRÃO FEBRABRAN
*  Contato: vhmarini@gmail.com
*  Likedin: https://www.linkedin.com/in/vitor-hugo-nunes-marini-10686881/
*   ## ARQUIVO EXPORTADOR DE REMESSA BANCO BANCOOB ( Sicoob, Paulista ou Cocrealpa )
*/

## ANTENÇÃO!!!! ANTES DE QUALQUER PROJETO, LEMBRE-SE DE INCLUIR SUAS FUNÇÕES DE CONEXÕES COM ( BANCO DE DADOS, FUNÇÕES EXPONTES E DERIVADOS )

/*
        ## RESPEITE RIGOROZAMENTE OS ESPAÇOS VAZIOS, EM BRANCO E ZERADOS ###
]*/

date_default_timezone_set('America/Sao_paulo');
error_reporting(E_ALL);
    
//Tratamento das variáveis passadas como parâmetro... ( Pode ser passado por Get, Post, Banco, etc... )        
$juros_mes            = str_replace(".","",moeda($_GET['juros_mes']));      //Porcentagem de juros por dia...
$multa_atraso         = moeda($_GET['multa_atraso']);                       //Porcentagem de multa por atraso...
$desconto             = moeda($_GET['desconto']);                           //Porcentagem de desconto...            
        
//Código do Banco...
if($_GET['banco_remessa'] == 'bradesco'){
    $codigo_banco = Cnab\Banco::BRADESCO;
    $nome_banco   = 'bradesco';            
} else {
    print json_encode(
        array(
            "mensagem" => "O banco selecionado não está implementado para geração do arquivo de remessa!",
            "status"   => "erro",
            "ok"       => FALSE
        )
    );
    exit;
}

//Diretório do arquivo onde serão armazenados os arquivos de remessa...
$diretorio   = "arquivos/{$nome_banco}/remessa/{$_GET['input_remessa']}/";
$nomeArquivo = $_GET['nome_arquivo'];    
$sequencial  = 1;  
$qtdRegistro = 0;
    
//VARIÁVEIS QUE ESTARÃO COM VALORES FIXOS. 
$loteServico    = "0000";
$data           = date('dmy');
$hora           = date('hms');
$tipoRegistro   = "0";
$tipoInscricao  = "2";
$contaNumero    = explode('-', $dadosBanco->fields['codigo'])[0];
$contaNumeroDV  = explode('-', $dadosBanco->fields['codigo'])[1];               
$cont           = 1;        
$loteHeader     = 1;        
$remessa        = array();
$sequencialSeg  = 1;

//Header do Arquivo.
$headerArquivo = array();        

/*001 - 001*/$headerArquivo[] = "0";
/*002 - 002*/$headerArquivo[] = "1";
/*002 - 009*/$headerArquivo[] = "REMESSA"; 
/*010 - 011*/$headerArquivo[] = "01";
/*012 - 026*/$headerArquivo[] = str_pad( "COBRANCA",                15,     " ", STR_PAD_RIGHT);
/*027 - 046*/$headerArquivo[] = str_pad( $numero_convenio,          20,     "0", STR_PAD_LEFT );
/*053 - 076*/$headerArquivo[] = str_pad( $razao_social_emissor,     30,     " ", STR_PAD_LEFT );        
/*077 - 079*/$headerArquivo[] = $codigo_banco;
/*080 - 094*/$headerArquivo[] = str_pad( "BRADESCO",                15,     " ", STR_PAD_RIGHT);        
/*095 - 100*/$headerArquivo[] = "{$data}";
/*101 - 108*/$headerArquivo[] = str_pad( " ",                       8,      " ", STR_PAD_LEFT );                
/*109 - 110*/$headerArquivo[] = str_pad( "MX",                      2,      " ", STR_PAD_LEFT );                
/*011 - 117*/$headerArquivo[] = str_pad( $numero_remessa,           7,      "0", STR_PAD_LEFT );
/*118 - 394*/$headerArquivo[] = str_pad( " ",                     277,      " ", STR_PAD_LEFT );        
/*395 - 400*/$headerArquivo[] = str_pad( $sequencialSeg,            6,      "0", STR_PAD_LEFT );

$remessa[$cont] = $headerArquivo;
$cont++;
$loteHeader ++;    
$sequencialSeg++; 

//Busca todos os dados do título...
#BUSCA DOS TÍTULOS NA SUA BASE OU NO QUE ESTARÁ SENDO PASSADO COMO PARÂMETRO

#ATRIBUÍNDO O NÚMERO DA CARTEIRA
$carteira = $numero_carteira;
        
while(!$titulo->EOF){
    #VALIDANDO OS NÚMEROS DOS TÍTULOS ( Existem empresas que fixam uma quantidade maior que de 10 caracteres, e no banco Bradescon temos o problema dessa limitação de 10 caracteres )
    $tituloInteiro          = explode("/",$titulo->fields['titulo']);
    $primeiraParteTitulo    = $tituloInteiro[0];
    $segundaParteTitulo     = (strlen($tituloInteiro[1]) == 3 ? $tituloInteiro[1] : str_replace("0","", $tituloInteiro[1]));
    $tituloMontado          = $primeiraParteTitulo."/".$segundaParteTitulo;
    
    #VALIDANDO A DATA DE VENCIMENTO
    $diaVencimento          = str_replace("/", "-", dataBrasil($titulo->fields['data_vencimento']));
    $dataProtesto           = str_replace("/", "", date('d/m/Y', strtotime("+1 days",strtotime($diaVencimento))));
    $dataVencimento         = str_replace("/", "", dataBrasil($titulo->fields['data_vencimento']));
    $dataVenct              = substr($dataVencimento, 0,4).substr($dataVencimento, 6,7);    
    $dataEmissaoTit         = str_replace("/", "", dataBrasil($titulo->fields['data_emissao']));
    $dataEmiss              = substr($dataEmissaoTit, 0,4).substr($dataEmissaoTit, 6,7);

    #VALIDANDO O CEP DA EMPRESA QUE IRÁ RECEBER O TÍTULO
    $cepLimpo               = str_replace("-", "", $titulo->fields['cep']);
    $tamanhoCep             = strlen($cepLimpo);
    if($tamanhoCep == 8){
        $cep1 = substr($titulo->fields['cep'], 0,5);
        $cep2 = substr($titulo->fields['cep'], 5, 8);
    }

    //Tipo de pessoa...
    $tipo_sacador = $pessoa == "F" ? "1" : "2";

    #VALIDANDO OS JUROS DIÁRIOS
    $juros_por_dia = (($valor_titulo * ($juros_mes / 100)) / 30);

    #VALIDANDO A MULTA
    $data_multa_aux         = new DateTime($titulo->fields['data_vencimento']);
    $data_multa             = $data_multa_aux->format('Y-m-d');
    $data_multa_aux->add(new DateInterval('P1D')); //Add 1 dia...

    //RegistroTransacao Tipo 1
    $regUm = array();

    /*001 - 001*/$regUm[] = 1;
    /*002 - 006*/$regUm[] = str_pad("0",                            5,  "0", STR_PAD_LEFT);                              
    /*007 - 007*/$regUm[] = str_pad("0",                            1,  "0", STR_PAD_LEFT);                
    /*008 - 012*/$regUm[] = str_pad("0",                            5,  "0", STR_PAD_LEFT);
    /*013 - 019*/$regUm[] = str_pad("0",                            7,  "0", STR_PAD_LEFT);
    /*020 - 020*/$regUm[] = str_pad("0",                            1,  "0", STR_PAD_LEFT);
    /*021 - 021*/$regUm[] = "0";
    /*022 - 024*/$regUm[] = "000";       // Carteira
    /*025 - 029*/$regUm[] = "00000";     // Agência
    /*030 - 036*/$regUm[] = "0000000";   // Conta
    /*037 - 037*/$regUm[] = "0";         // Conta - DV
    /*038 - 062*/$regUm[] = str_pad( $cnpj_cpf_cgc_titulo,         25, "0", STR_PAD_LEFT );
    /*063 - 065*/$regUm[] = str_pad( "0",                           3,  "0", STR_PAD_LEFT );
    /*066 - 066*/$regUm[] = "2";
    /*067 - 070*/$regUm[] = "0500";                
    /*071 - 081*/$regUm[] = str_pad( "0",                           11, "0", STR_PAD_LEFT );
    /*082 - 082*/$regUm[] = str_pad( "0",                           1,  "0", STR_PAD_LEFT );
    /*083 - 092*/$regUm[] = str_pad( "0",                           10, "0", STR_PAD_LEFT );
    /*093 - 093*/$regUm[] = "1";
    /*094 - 094*/$regUm[] = "N";
    /*095 - 104*/$regUm[] = str_pad( " ",                           10, " ", STR_PAD_LEFT ); 
    /*105 - 105*/$regUm[] = " ";
    /*106 - 106*/$regUm[] = "2";
    /*107 - 108*/$regUm[] = str_pad( " ",                           2,  " ", STR_PAD_LEFT );                                 
    /*109 - 110*/$regUm[] = "01";
    /*111 - 120*/$regUm[] = str_pad( $tituloMontado, 10, "0", STR_PAD_LEFT );
    /*121 - 126*/$regUm[] = $dataVenct;
    /*127 - 139*/$regUm[] = str_pad( str_replace(".", "", $valor_titulo), 13, 0, STR_PAD_LEFT );                
    /*140 - 142*/$regUm[] = str_pad( "0",                           3,  "0", STR_PAD_LEFT);
    /*143 - 147*/$regUm[] = str_pad( "0",                           5,  "0", STR_PAD_LEFT);
    /*148 - 149*/$regUm[] = "01";
    /*150 - 150*/$regUm[] = "N";
    /*151 - 156*/$regUm[] = $dataEmiss;
    /*157 - 158*/$regUm[] = (!empty($dias_protestar) ? "06" : "00");
    /*159 - 160*/$regUm[] = (!empty($dias_protestar) ? str_pad( $dias_protestar,  2, "0", STR_PAD_LEFT ) : "00");
    /*161 - 173*/$regUm[] = str_pad( "0",                           13, "0", STR_PAD_LEFT );
    /*174 - 179*/$regUm[] = str_pad( "0",                           6,  "0", STR_PAD_LEFT );
    /*180 - 192*/$regUm[] = str_pad( "0",                           13, "0", STR_PAD_LEFT );
    /*193 - 205*/$regUm[] = str_pad( "0",                           13, "0", STR_PAD_LEFT );
    /*206 - 218*/$regUm[] = str_pad( "0",                           13, "0", STR_PAD_LEFT );
    /*219 - 220*/$regUm[] = str_pad( $tipo_sacador,                 2,  "0", STR_PAD_LEFT );
    /*221 - 234*/$regUm[] = str_pad( substr($cpf_cnpj_cgc_titulo,0,39),   14, "0", STR_PAD_LEFT ); 
    /*235 - 274*/$regUm[] = str_pad( substr($razao_social_titulo,0,39), 40, " ", STR_PAD_LEFT );
    /*274 - 314*/$regUm[] = str_pad( substr(retiraAcentos($endereco_completo_titulo),0,39),   40, " ", STR_PAD_LEFT );               
    /*315 - 326*/$regUm[] = str_pad(substr($mensagem_titulo,0,12), 12, " ", STR_PAD_LEFT);
    /*327 - 331*/$regUm[] = str_pad( str_replace("-", "", $cep1),   5,  "0", STR_PAD_LEFT);
    /*332 - 334*/$regUm[] = str_pad( str_replace("-", "", $cep2),   3,  "0", STR_PAD_LEFT);
    /*335 - 394*/$regUm[] = str_pad( "",                            60, " ", STR_PAD_LEFT );
    /*395 - 400*/$regUm[] = str_pad( $sequencialSeg,                6,  "0", STR_PAD_LEFT );

    $remessa[$cont] = $regUm;
    $cont ++;               
    $sequencialSeg++;   
    $titulo->MoveNext();
} 

//TraiilerRemessa
$traillerRemessa = array();

/*001 - 001*/$traillerRemessa[] = "9";
/*002 - 394*/$traillerRemessa[] = str_pad( " ",                  393, " ", STR_PAD_LEFT );
/*395 - 400*/$traillerRemessa[] = str_pad( $sequencialSeg,       6,   "0", STR_PAD_LEFT );

$remessa[$cont] = $traillerRemessa;
$cont ++;
$sequencialSeg ++;

//Gerando o arquivo...
if(gravar($remessa, $diretorio, $nomeArquivo)){
    $mensagem = "Operação realizada com sucesso!";
    $ok = TRUE;
} else {
    $mensagem = "Um possivel erro não identificado impediu à geração do arquivo de remessa!";
    $ok = FALSE;
}

//Retorno do arquivo...
print json_encode(
    array(
        "mensagem" => $mensagem,
        "status"   => "",
        "ok"       => $ok
    )
);        
        
#FUNÇÃO RESPONSÁVEL POR GRAVAR OS DADOS DO ARRAY NO DOCUMENTO .TXT
function gravar($texto, $local, $nome){
    //Variável arquivo armazena o nome e extensão do arquivo.
    $arquivo = $local.$nome;                                  

    //Variável $fp armazena a conexão com o arquivo e o tipo de ação.
    $fp = fopen($arquivo, "wb");

    foreach ($texto as $valor){
        $x = 1;
        if ( count( $valor[$x] ) > 1 ) {
            foreach ($valor[$x] as $valor1){
                fwrite($fp, implode( $valor1 ) . "\r\n" );
            }
        }
        else {
            fwrite($fp, implode( $valor ) . "\r\n" );
        }
        $x ++;
    }

    $numero_remessa = file_get_contents($local.'numero_remessa.txt') + 1;
    if(file_put_contents($local.'numero_remessa.txt', str_pad($numero_remessa,6,0,STR_PAD_LEFT))){
        return true;                
    }else{
        return false;
    }

}
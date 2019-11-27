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
           
//Código do Banco... ( Caso queira deixar engessado fixe o código na mão )
if($_GET['banco_remessa'] == 'bancoob'){
    $codigo_banco = Cnab\Banco::SICOOB;
    $nome_banco   = 'bancoob';            
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

//Agência e conta banco...
/*
    Busque o banco, seja pela seleção comum SQL ou por parâmetros.
*/
$numero_conta    = ""; // Somente a quantia permitida pelo seu banco ( Consultar manual do banco )
$numero_dv_conta = ""; // Somente a quantia permitida pelo seu banco ( Consultar manual do banco )

//Diretório do arquivo onde serão armazenados os arquivos de remessa...
$diretorio = "arquivos/{$nome_banco}/remessa/{$_GET['input_remessa']}/";
$nomeArquivo = $_GET['nome_arquivo'];
    
//Definição dos registros sequenciais Inicial
$sequencial   = 1;  
$qtdRegistro  = 0;
$loteHeader   = 1;
    
//Variáveis totalizadoras Quantidade Registro Trailler Lote  ( SICOOB Para quem utiliza esse modelo )
$qtdRegistroCarteiraSimples    = 0;   
$qtdRegistroCarteiraVinculada  = 0;    
$qtdRegistroCarteiraCaucionada = 0;    
$qtdRegistroCarteiraDescontada = 0;
$vlrTotalCartSimples           = 0;
$vlrTotalCartVinculada         = 0;
$vlrTotalCartCaucionada        = 0;
$vlrTotalCartDescontada        = 0;    

//Início das Variáveis FIXAS, Contadoras, arrays e datas;
$remessa                = array();
$cont                   = 0;
$loteServico            = "0000";
$data                   = date('dmY');
$hora                   = date('hms');
$tipoRegistro           = "0";
$tipoInscricao          = "2";
$contaNumero            = $numero_conta;
$contaNumeroDV          = $numero_dv_conta;

################## INÍCIO DA CRIAÇÃO DO ARQUIVO DE REMESSA, ATRIBUÍNDO VALORES A CADA CAMPO EXATO DO ARRAY ############################

//HEADER DO ARQUIVO, AQUI DEVE-SE INCLUIR OS DADOS SUA EMPRESA, SEMPRE VALIDANDO COM OS TAMANHOS ATUAIS DO SEU BANCO
$headerArquivo = array();         
   
#HEADER COM AS POSIÇÕES QUE ESTÃO NO MANUAL DO BANCO ( FACILITADORAS NA HORA DA MANUTENÇÃO )   
/*001 - 003*/$headerArquivo[] = $codigo_banco;
/*004 - 007*/$headerArquivo[] = $loteServico;
/*008 - 008*/$headerArquivo[] = $tipoRegistro; 
/*009 - 017*/$headerArquivo[] = str_pad( " ",                               9,  " ", STR_PAD_LEFT );
/*018 - 018*/$headerArquivo[] = $tipoInscricao;
/*019 - 032*/$headerArquivo[] = str_pad( $cnpj_cpf_cgc,                     14, "0", STR_PAD_LEFT );
/*033 - 052*/$headerArquivo[] = str_pad( "",                                20, " ", STR_PAD_LEFT );
/*053 - 057*/$headerArquivo[] = str_pad( $codigo_banco,                      5, "0", STR_PAD_LEFT );
/*058 - 058*/$headerArquivo[] = " ";
/*059 - 070*/$headerArquivo[] = str_pad( $contaNumero,                      12, "0", STR_PAD_LEFT );        
/*071 - 071*/$headerArquivo[] = str_pad( $contaNumeroDV,                    1,  "0", STR_PAD_LEFT );
/*072 - 072*/$headerArquivo[] = str_pad( "",                                1,  "0", STR_PAD_LEFT );
/*073 - 102*/$headerArquivo[] = str_pad( "LINOFORTE MOVEIS LTDA",           30, " ", STR_PAD_LEFT );        
/*103 - 132*/$headerArquivo[] = str_pad( "SICOOB",                          30, " ", STR_PAD_LEFT );                                
/*133 - 142*/$headerArquivo[] = str_pad( " ",                               10, " ", STR_PAD_LEFT );
/*143 - 143*/$headerArquivo[] = "1";
/*144 - 151*/$headerArquivo[] = "{$data}";
/*152 - 157*/$headerArquivo[] = "{$hora}";
/*158 - 163*/$headerArquivo[] = str_pad( "{$sequencial}",                   6,  "0", STR_PAD_LEFT );
/*164 - 166*/$headerArquivo[] = "081";
/*167 - 171*/$headerArquivo[] = str_pad( "",                                5,  "0", STR_PAD_LEFT );                            
/*172 - 191*/$headerArquivo[] = str_pad( "",                                20, " ", STR_PAD_LEFT );
/*192 - 211*/$headerArquivo[] = str_pad( "",                                20, " ", STR_PAD_LEFT );
/*212 - 240*/$headerArquivo[] = str_pad( "",                                29, " ", STR_PAD_LEFT );

#INSERINDO O HEADER DENTRO DA REMESSA NA POSIÇÃO ATUAL DO CONT - ATUALIZANDO O CONT
$remessa[$cont] = $headerArquivo;
$cont++;
    
#HEADER DO LOTE ( AQUI SERÇÃO INCLUSOS OS DADOS BANCÁRIOS PARA QUE O BANCO IDENTIFIQUE A REMESSA E VALIDE OS CAMPOS )
$headerLote = array();

/*001 - 003*/$headerLote[] = "756";
/*004 - 007*/$headerLote[] = str_pad( $loteHeader,                          4,  "0", STR_PAD_LEFT );
/*008 - 008*/$headerLote[] = "1";
/*009 - 009*/$headerLote[] = "R";
/*010 - 011*/$headerLote[] = "01";
/*012 - 013*/$headerLote[] = "  ";
/*014 - 016*/$headerLote[] = "040";
/*017 - 017*/$headerLote[] = " ";
/*018 - 018*/$headerLote[] = "2";    
/*019 - 033*/$headerLote[] = str_pad( $cnpj_cpf_cgc,                        15,  "0", STR_PAD_LEFT );
/*034 - 053*/$headerLote[] = str_pad( " ",                                  20,  " ", STR_PAD_LEFT );
/*054 - 058*/$headerLote[] = str_pad( $codigo_banco,                         5,  "0", STR_PAD_LEFT );    
/*059 - 059*/$headerLote[] = "0";
/*060 - 071*/$headerLote[] = str_pad( $contaNumero,                         12,  "0", STR_PAD_LEFT );
/*072 - 072*/$headerLote[] = str_pad( $contaNumeroDV,                       1,   "0", STR_PAD_LEFT );  
/*073 - 073*/$headerLote[] = " ";
/*074 - 103*/$headerLote[] = str_pad( $razao_social_empresa,                30,  " ", STR_PAD_LEFT );   
/*104 - 143*/$headerLote[] = str_pad( $mensagem_40_carac,                   40,  " ", STR_PAD_LEFT );     
/*144 - 183*/$headerLote[] = str_pad( " ",                                  40,  "0", STR_PAD_LEFT );
/*184 - 191*/$headerLote[] = str_pad( $numero_remessa,                       8,  "0", STR_PAD_LEFT );
/*192 - 199*/$headerLote[] = $data;
/*200 - 207*/$headerLote[] = str_pad( "0",                                  8,   "0", STR_PAD_LEFT );    
/*208 - 240*/$headerLote[] = str_pad( " ",                                  33,  " ", STR_PAD_LEFT );

$remessa[$cont] = $headerLote;
$cont++;
    
#INICIALIZANDO OS TOTALIZADORES
$loteTrailler  = 1;
$loteP         = 1;
$loteQ         = 1;
$loteR         = 1;
$loteS         = 1;
$sequencialSeg = 1; 
            
            
//Busca todos os dados do título...
#BUSCA DOS TÍTULOS NA SUA BASE OU NO QUE ESTARÁ SENDO PASSADO COMO PARÂMETRO
            
          
#REPETIÇÃO E LEITURA DOS TÍTULOS QUE SERÃO EXPORTADOS -- NO MEU CASO EXECUTO UM WHILE ( NADA IMPEDE DE SER OUTRO TIPO DE LEITURA    )
while(!$titulo->EOF){    
    #AQUI SERÁ INSERIDO OS DADOS DE CADA TÍTULO BUSCADO EM SEU BANCO OU EM SUA PLATAFORMA
    
    #AJUSTANDO DATA DE PROTESTO, CEP, TAMANHO CORRETO DIA DE VENCIMENTO E ETC....
    $diaVencimento  = str_replace("/", "-", dataBrasil($titulo->fields['data_vencimento']));
    $dataProtesto   = str_replace("/",  "",  date('d/m/Y', strtotime("+1 days",strtotime($diaVencimento))));
    $cepLimpo       = str_replace(".",  "",  str_replace("-", "", $titulo->fields['cep']));
    $tamanhoCep     = strlen($cepLimpo);
    if($tamanhoCep == 8){
        $cep1 = substr(str_pad(substr($cepLimpo, 0,5), 5 ,"0",STR_PAD_LEFT),0,5);
        $cep2 = substr(str_pad(substr($cepLimpo, 5, 8), 3 ,"0",STR_PAD_LEFT),0,3);
    }
    
    #AJUSTANDO O TIPO DE PESSOA ( CASO SEJA PESSOA FÍSICA VALOR 1 E JURÍDICA 2 )
    $tipo_sacador = $titulo-fields['pessoa'] == "F" ? "1" : "2";
    
    #AJUSTANDO OS JUROS DIÁRIOS
    $juros_por_dia = (($valor_titulo * ($juros_mes / 100)) / 30);
    
    #AJUSTANDO A MULTA OU MORA
    $data_multa_aux = new DateTime($titulo->fields['data_vencimento']);
    $data_multa_aux->add(new DateInterval('P1D')); //Add 1 dia...
    $data_multa = str_replace("/", "",$data_multa_aux->format('d/m/Y'));
    
    #INÍCIO DO SEGUIMENTO "P"
    $segP = array();
    
    /*001 - 003*/$segP[] = $codigo_banco;
    /*004 - 007*/$segP[] = str_pad($loteHeader,                     4, "0", STR_PAD_LEFT);
    /*008 - 008*/$segP[] = "3";
    /*009 - 013*/$segP[] = str_pad( $sequencialSeg ,                5, 0, STR_PAD_LEFT );
    /*014 - 014*/$segP[] = "P";
    /*015 - 015*/$segP[] = " ";
    /*016 - 017*/$segP[] = $movimento_remessa;
    /*018 - 022*/$segP[] = str_pad( $codigo_banco,                  5, "0", STR_PAD_LEFT );
    /*023 - 023*/$segP[] = "0";
    /*024 - 035*/$segP[] = str_pad( $contaNumero,                   12, 0, STR_PAD_LEFT );
    /*036 - 036*/$segP[] = str_pad( $contaNumeroDV,                 1, "0", STR_PAD_LEFT );
    /*037 - 037*/$segP[] = str_pad( "",                             1, " ", STR_PAD_LEFT );
    /*038 - 057*/$segP[] = (str_pad( "", 10, "0", STR_PAD_LEFT )."01"."01"."1".str_pad( "", 5, " ", STR_PAD_RIGHT));
    /*058 - 058*/$segP[] = $carteira;
    /*059 - 059*/$segP[] = "0";
    /*060 - 060*/$segP[] = " ";                
    /*061 - 061*/$segP[] = "1";
    /*063 - 063*/$segP[] = "1";
    /*063 - 077*/$segP[] = str_pad( $numero_empresa,                15, 0, STR_PAD_LEFT );                                
    /*078 - 085*/$segP[] = str_replace("/", "", dataBrasil($data_vencimento));
    /*086 - 100*/$segP[] = str_pad( str_replace(".", "", $valor),                 15, 0, STR_PAD_LEFT );
    /*101 - 105*/$segP[] = "00000"; 
    /*106 - 106*/$segP[] = " ";
    /*107 - 108*/$segP[] = $especie_titulo; 
    /*109 - 109*/$segP[] = "N"; // A - Aceite ou N - Não aceite                                                
    /*110 - 117*/$segP[] = str_replace("/", "", dataBrasil($data_emissao));
    /*118 - 118*/$segP[] = ( $_GET['juros_mes'] > 0 ? "2" : "" ); //Juros Mora
    /*119 - 126*/$segP[] = ( $_GET['juros_mes'] > 0 ? $data_multa : "00000000");//Data Juros Mora
    /*127 - 141*/$segP[] = ( $_GET['juros_mes'] > 0 ? str_pad($juros_mes,15,"0", STR_PAD_LEFT  ): str_pad("",15,"0", STR_PAD_LEFT  )); //Juros Mora %
    /*142 - 142*/$segP[] = "0"; //Desconto 1
    /*143 - 150*/$segP[] = "00000000"; // Data Desconto 1
    /*151 - 165*/$segP[] = str_pad( "",                             15, 0, STR_PAD_LEFT ); // Valor Desconto 1
    /*166 - 180*/$segP[] = str_pad( "",                             15, 0, STR_PAD_LEFT ); // Valor IOF Recolhido
    /*181 - 195*/$segP[] = str_pad( "",                             15, 0, STR_PAD_LEFT ); // Valor Abatimento
    /*196 - 220*/$segP[] = str_pad( "",                             25, " ", STR_PAD_LEFT ); // Campo destinando para uso do Beneficiário
    /*221 - 221*/$segP[] = '1'; // Código para protesto
    /*222 - 223*/$segP[] = ($protesto == 3 ? "00" : $dias_protestar);
    /*224 - 224*/$segP[] = "0";
    /*225 - 227*/$segP[] = str_pad( "",                             3, " ", STR_PAD_LEFT );
    /*228 - 229*/$segP[] = "09";
    /*230 - 239*/$segP[] = str_pad( "",                             10, 0, STR_PAD_LEFT );
    /*240 - 240*/$segP[] = " ";

    $sequencialSeg ++;
    $remessa[$cont] = $segP;
    $cont ++;

    #INÍCIO DO SEGUIMENTO "Q"
    $segQ = array();

    /*001 - 003*/$segQ[] = $codigo_banco;
    /*004 - 007*/$segQ[] = str_pad($loteHeader, 4, "0", STR_PAD_LEFT);
    /*008 - 008*/$segQ[] = "3";
    /*009 - 013*/$segQ[] = str_pad( $sequencialSeg, 5, 0, STR_PAD_LEFT );
    /*014 - 014*/$segQ[] = "Q";
    /*015 - 015*/$segQ[] = " ";
    /*016 - 017*/$segQ[] = $motivo_remessa; 
    /*018 - 018*/$segQ[] = $tipo_sacador; 
    /*019 - 033*/$segQ[] = substr(str_pad( retiraAcentos($titulo_cpf_cnpj_cgc)      ,   15,   0, STR_PAD_LEFT ),0,15);
    /*034 - 073*/$segQ[] = substr(str_pad( retiraAcentos($titulo_razao_social)      ,   40, " ", STR_PAD_LEFT ),0,40);
    /*074 - 113*/$segQ[] = substr(str_pad( retiraAcentos($titulo_endereco_completo) ,   40, " ", STR_PAD_LEFT ),0,40);
    /*114 - 128*/$segQ[] = substr(str_pad( retiraAcentos($titulo_bairro)            ,   15, " ", STR_PAD_LEFT ),0,15);
    /*129 - 133*/$segQ[] = $cep1;
    /*134 - 136*/$segQ[] = $cep2;
    /*137 - 151*/$segQ[] = str_pad(retiraAcentos($nome_cidade), 15, " ", STR_PAD_LEFT );
    /*152 - 153*/$segQ[] = $estado_uf;
    /*154 - 154*/$segQ[] = "2"; //TIPO INSTRICAÇÃO SACADO/AVALISTA
    /*155 - 169*/$segQ[] = str_pad( $cnpj_cpf_cgc,   15,   0, STR_PAD_LEFT ); // CAMPO DOS DADOS DA EMPRESA EMISSORA DOS TÍTULOS
    /*170 - 209*/$segQ[] = str_pad( $razap_social,   40, " ", STR_PAD_LEFT ); // CAMPO DOS DADOS DA EMPRESA EMISSORA DOS TÍTULOS
    /*210 - 212*/$segQ[] = str_pad(            "",    3,   0, STR_PAD_LEFT );
    /*213 - 232*/$segQ[] = str_pad(            "",   20, " ", STR_PAD_LEFT );
    /*233 - 240*/$segQ[] = str_pad(            "",    8, " ", STR_PAD_LEFT );

    $sequencialSeg ++;
    $remessa[$cont] = $segQ;
    $cont ++;
    
    #INÍCIO DO SEGMENTO R
    $segR = array();

    /*001 - 003*/$segR[] = $codigo_banco;
    /*004 - 007*/$segR[] = str_pad($loteHeader,                     4, "0", STR_PAD_LEFT);
    /*008 - 008*/$segR[] = "3";
    /*009 - 013*/$segR[] = str_pad( $sequencialSeg,               5, 0, STR_PAD_LEFT );
    /*014 - 014*/$segR[] = "R";
    /*015 - 015*/$segR[] = " ";
    /*016 - 017*/$segR[] = $movimento_remessa;
    /*018 - 018*/$segR[] = "0"; //desconto 0, 1 ou 2
    /*019 - 026*/$segR[] = "00000000";
    /*027 - 041*/$segR[] = str_pad( "",                             15, 0, STR_PAD_LEFT );
    /*042 - 042*/$segR[] = "0";
    /*043 - 050*/$segR[] = "00000000";
    /*051 - 065*/$segR[] = str_pad( "",                             15, "0", STR_PAD_LEFT );
    /*066 - 066*/$segR[] = "0"; 
    /*067 - 074*/$segR[] = "00000000";
    /*075 - 089*/$segR[] = str_pad( "",                             15, 0, STR_PAD_LEFT );
    /*090 - 099*/$segR[] = str_pad( "",                             10, " ", STR_PAD_LEFT );
    /*100 - 139*/$segR[] = str_pad( "",                             40, " ", STR_PAD_LEFT );
    /*140 - 179*/$segR[] = str_pad( "",                             40, " ", STR_PAD_LEFT );
    /*180 - 199*/$segR[] = str_pad( "",                             20, " ", STR_PAD_LEFT );
    /*200 - 207*/$segR[] = str_pad( "",                             8, 0, STR_PAD_LEFT );
    /*208 - 210*/$segR[] = "000";
    /*211 - 215*/$segR[] = "00000";
    /*216 - 216*/$segR[] = " ";
    /*217 - 228*/$segR[] = str_pad( "",                             12, "0", STR_PAD_LEFT );
    /*229 - 229*/$segR[] = " ";
    /*230 - 230*/$segR[] = " ";
    /*231 - 231*/$segR[] = 0;
    /*232 - 240*/$segR[] = str_pad( "",                             9, " ", STR_PAD_LEFT );

    $sequencialSeg ++;
    $remessa[$cont] = $segR;
    $cont ++;
    
    #INÍCIO DO SEGMENTO "S"
    $segS = array();
    $numLinhaMensagemS = 0;
    $mensagem = "";
    $impressao = "1";
    
    /*001 - 003*/$segS[] = $codigo_banco;
    /*004 - 007*/$segS[] = str_pad($loteHeader,                     4, "0", STR_PAD_LEFT);
    /*008 - 008*/$segS[] = "3";
    /*009 - 013*/$segS[] = str_pad( $sequencialSeg++,               5, 0, STR_PAD_LEFT );
    /*014 - 014*/$segS[] = "S";
    /*015 - 015*/$segS[] = " ";
    /*016 - 017*/$segS[] = $_GET['movimento_remessa'];
    /*018 - 018*/$segS[] = "1"; 
    //Para tipo de Impressão
    if($impressao == "1" || $impressao == "2"){
        /*019 - 020*/$segS[] = str_pad( $numLinhaMensagemS, 2, "0", STR_PAD_LEFT ); 
        /*021 - 160*/$segS[] = str_pad( $mensagem, 140, " ", STR_PAD_LEFT ); 
        /*161 - 162*/$segS[] = "00";
        /*163 - 240*/$segS[] = str_pad( "", 78, " ", STR_PAD_LEFT ); 
    }else{
        $segS[] = 00;
        $segS[] = str_pad( "", 78, " ", STR_PAD_LEFT );
        $segS[] = 3;
        $segS[] = str_pad( "", 40, " ", STR_PAD_LEFT );
        $segS[] = str_pad( "", 40, " ", STR_PAD_LEFT );
        $segS[] = str_pad( "", 40, " ", STR_PAD_LEFT );
        $segS[] = str_pad( "", 40, " ", STR_PAD_LEFT );
        $segS[] = str_pad( "", 40, " ", STR_PAD_LEFT );
        $segS[] = str_pad( "", 22, " ", STR_PAD_LEFT );
    }                                                                                               
    
    $remessa[$cont] = $segS;
    $cont++;
    
    if($carteira == 1){
        $qtdRegistroCarteiraSimples ++;
        $vlrTotalCartSimples += $valor_titulo;
    }
    if($carteira == 2){
        $qtdRegistroCarteiraVinculada ++;
        $vlrTotalCartVinculada += $valor_titulo;
    }
    if($carteira == 2){
        $qtdRegistroCarteiraCaucionada ++;
        $vlrTotalCartCaucionada += $valor_titulo;
    }
    if($_GET['carteira'] == 3){
        $qtdRegistroCarteiraDescontada ++;
        $vlrTotalCartDescontada += $valor_titulo;
    }                                
    
    $qtdRegistro += $sequencialSeg;
    $titulo->MoveNext();
}
            
//Registro TRAILLER DO LOTE
$traillerLote = array();

/*001 - 003*/$traillerLote[] = $codigo_banco;
/*004 - 007*/$traillerLote[] = str_pad($loteHeader, 4, "0", STR_PAD_LEFT);
/*008 - 008*/$traillerLote[] = "5";
/*009 - 017*/$traillerLote[] = str_pad( "", 9, " ", STR_PAD_LEFT );
/*018 - 023*/$traillerLote[] = str_pad( $qtdRegistro, 6, "0", STR_PAD_LEFT );
/*024 - 029*/$traillerLote[] = str_pad( $qtdRegistroCarteiraSimples, 6, "0", STR_PAD_LEFT );

/*030 - 046*/$traillerLote[] = str_pad( str_replace(".", "", $vlrTotalCartSimples), 17, "0", STR_PAD_LEFT );
/*047 - 052*/$traillerLote[] = str_pad( $qtdRegistroCarteiraVinculada, 6, 0, STR_PAD_LEFT );
/*053 - 069*/$traillerLote[] = str_pad( str_replace(".", "", $vlrTotalCartVinculada), 17, 0, STR_PAD_LEFT );
/*070 - 075*/$traillerLote[] = str_pad( $qtdRegistroCarteiraCaucionada, 6, 0, STR_PAD_LEFT );
/*076 - 092*/$traillerLote[] = str_pad( str_replace(".", "", $vlrTotalCartCaucionada), 17, 0, STR_PAD_LEFT );
/*093 - 098*/$traillerLote[] = str_pad( $qtdRegistroCarteiraDescontada, 6, 0, STR_PAD_LEFT );
/*099 - 115*/$traillerLote[] = str_pad( str_replace(".", "", $vlrTotalCartDescontada), 17, 0, STR_PAD_LEFT );
/*116 - 123*/$traillerLote[] = str_pad( "", 8, " ", STR_PAD_LEFT );
/*124 - 240*/$traillerLote[] = str_pad( "", 117, " ", STR_PAD_LEFT );

//Registro TRAILLER DO ARQUIVO
$remessa[$cont] = $traillerLote;
    
$loteP ++;
$loteQ ++;
$loteR ++;
$loteS ++;


$traillerArquivo = array();

/*001 - 003*/$traillerArquivo[] = "756";
/*004 - 007*/$traillerArquivo[] = "9999";
/*008 - 008*/$traillerArquivo[] = 9;
/*009 - 017*/$traillerArquivo[] = str_pad( " ",                         9,   " ", STR_PAD_LEFT );
/*018 - 023*/$traillerArquivo[] = str_pad( $loteHeader,                 6,   "0", STR_PAD_LEFT);
/*024 - 029*/$traillerArquivo[] = str_pad( $qtdRegistro,                6,   "0", STR_PAD_LEFT );
/*030 - 035*/$traillerArquivo[] = str_pad( "0",                         6,   "0", STR_PAD_LEFT );
/*036 - 240*/$traillerArquivo[] = str_pad( " ",                         205, " ", STR_PAD_LEFT );

#INCREMENTANDO O VALOR FINAL DO TRAILLER
$remessa[$cont + 1] = $traillerArquivo;
    
#PROCESSO DE GERAÇÃO E GRAVAÇÃO DO ARQUIVO NO LOCAL ESPECÍFICO ( INFORMAR "REMESSA", "DIRETORIO", "NOME DO ARQUIVO" )
if(gravar($remessa, $diretorio, $nomeArquivo)){
    $mensagem = "Operação realizada com sucesso!";
    $ok = TRUE;    
} else {    
    $mensagem = "Um possivel erro não identificado impediu à geração do arquivo de remessa!";
    $ok = FALSE;
}

#RETORNO PARA A PÁGINA E FINALIZANDO A GRAVAÇÃO, CASO QUEIRA EXPORTAR PARA UM DOWNLOAD DRIVER, NÃO HÁ PROBLEMA É SÓ BUSCAR O ARQUIVO DE TEXTO GERADO.
print json_encode(
    array(
        "mensagem" => $mensagem,
        "status"   => "",
        "ok"       => $ok
    )
);        
     
#FUNÃO CRIADA PARA GRAVAR OS DADOS SEQUENCIALMENTE INSERIDOS NO ARRAY E AGORA TRANSCRITOS NO .TXT
function gravar($texto, $local, $nome){
    //Variável arquivo armazena o nome e extensão do arquivo.
    $arquivo = $local.$nome;                                  
    
    //Variável $fp armazena a conexão com o arquivo e o tipo de ação.
    $fp = fopen($arquivo, "wb");

    foreach ($texto as $valor){
        $x = 1;
        if ( count( $valor[$x] ) > 1 ) {
            foreach ($valor[$x] as $valor1){
                fwrite($fp, implode(  mb_convert_encoding($valor1, 'UTF-8', 'ANSI') ) . "\r\n" );
            }
        }
        else {
            fwrite($fp, implode( $valor ) . "\r\n" );
        }
        $x ++;
    }
    
    
    $numero_remessa = file_get_contents($local.'numero_remessa.txt') + 1;
    
    file_put_contents($local.'numero_remessa.txt', str_pad($numero_remessa,6,0,STR_PAD_LEFT));
    
    fclose($fp);
    
    return true;
}
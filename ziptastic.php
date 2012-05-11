<?

//ini_set("display_errors", "1"); error_reporting(E_ALL ^ E_NOTICE);

require( 'ziptastic_db.php' ); // DB settings for db_connect()

function db_connect()
{
    $db = mysql_connect( DB_HOST, DB_USER, DB_PASS );
    mysql_select_db( DB_NAME );
}

function find_street( $street = false )
{
    $res = array();
    if ( $street )
    {
        db_connect();
        mysql_query( 'set group_concat_max_len=1024*1024' );
        $resource = mysql_query( "SELECT type_short, name, GROUP_CONCAT(DISTINCT CONCAT(IF(region='МОСКВА','Московская область,Москва',IF(region='САНКТ-ПЕТЕРБУРГ','Ленинградская область,Санкт-Петербург',CONCAT(region,',',city))),',',zip,IF(autonom=''&&area='','',CONCAT(',',autonom,',',area))) ORDER BY city,region SEPARATOR ';') AS localities  FROM ziptastic_street LEFT JOIN PIndx07 USING(zip) WHERE name LIKE '$street%' GROUP BY type_short, name ORDER BY type_weight DESC, name ASC LIMIT 20" );
        if ( $resource )
        {
            while( $row = mysql_fetch_assoc($resource) )
            {
                $row['localities'] = prepare_localities($row['localities']);
                $row['type_short'] = prepare_type_short($row['type_short']);
                $res[] = $row;
            }
        }
    }
    return $res;
}

function find_zip( $zip = 0 )
{
    $zip = (int)$zip;

    $res = 0;
    if ( $zip > 0 )
    {
        db_connect();
        $resource = mysql_query( "SELECT zip, region, autonom, area, city, city_1 FROM PIndx07 WHERE zip=$zip" );
        if ( $resource )
            $res = mysql_fetch_assoc( $resource );
    }
    return $res;
}

function mb_uc_first( $word, $need_lower_case = true )
{
    return mb_strtoupper( mb_substr($word, 0, 1, 'UTF-8'), 'UTF-8') 
           . mb_substr( $need_lower_case ? mb_convert_case($word, MB_CASE_LOWER, 'UTF-8') : $word, 1, mb_strlen($word), 'UTF-8');
}

function mb_uc_words( $string, $word_separators = ' ')
{   
    $string = mb_uc_first( $string ); 
    for ( $j = strlen( $word_separators ) - 1; $j >= 0; $j-- )
    {
        $words = mb_split( $word_separators[$j], $string );

        for ( $i = count($words)-1; $i >= 0; $i-- )
            $words[$i] = mb_uc_first( $words[$i], false );

        $string = implode( $word_separators[$j], $words );
    }
    return $string;
}

function prepare_type_short( $type_short )
{
    return ( mb_strlen($type_short, 'UTF-8') > 3 || mb_strpos( $type_short, '-' ) !== false )
        ? $type_short
        : $type_short.'.';
}

function prepare_localities( $localities )
{
    $locality = mb_split( ';', $localities );

    for( $i = count($locality)-1; $i >= 0; $i-- )
        $locality[$i] = prepare_locality( $locality[$i] );

    return implode( ';', $locality );
}

function prepare_locality($locality)
{
    list( $region, $city, $zip ) = mb_split( ',', $locality );
    $region = prepare_region( $region );
    $city   = prepare_city( $city );
    return implode( ',', array( $region, $city, $zip ) );
}

function prepare_region( $region )
{
    $region = mb_uc_first($region);
    if ( mb_strpos( $region, 'республика' ) !== false )
    {
        $region = str_replace( ' республика', '', $region );
        $region = 'Республика '.$region;
    }
    return $region;
}

function prepare_city( $city )
{
    $city = mb_uc_words( $city, ' -' );
    $city = str_replace( '-На-', '-на-', $city );
    return $city;
}

function array_compact( $array )
{
    foreach ( $array as $key => $val )
        if ( empty($val) )
            unset($array[$key]);

    return $array;
}

$query = addslashes( $_GET['q'] );

$res = preg_match('/^\d{6}$/', $query )
         ? find_zip( $query )
         : ( ( mb_strlen( $query ) < 2 || preg_match( '/\d.*\d.*\d/', $query ) ) 
            ? false
            : find_street( mb_uc_first($query) ) );

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8', TRUE);

if ( !empty($res) )
{
//print_r($res); exit;

    $json = json_encode(array_compact($res));
    $out = $_GET['callback'] 
              ? sprintf( '%s(%s);', $_GET['callback'], $json )
              : $json;
    echo $out;
}
else
{
    header( 'HTTP/1.0 404 Not Found' );
    echo '{}';
}

?>
<?php
declare(strict_types=1);
namespace App\Services;
class BadStyleService{
public function calculate($x,$y){
$result=$x+$y;
if($result>100){return true;}
return false;
}
public function format( $value ){
    return    trim($value)   ;
}
}

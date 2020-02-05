<?php

namespace MKDF\Datasets\Entity;

use MKDF\Core\Entity\Bucket;

class Dataset extends Bucket
{
    //Properties
    public $id;            //Dataset ID
    public $uuid;          //Dataset UUID
    public $title;         //Data Title
    public $description;   //Dataset Description
    public $type;          //Dataset Type
    public $user_id;         //Owner of the dataset

    public static function genUuid () {
        $data = openssl_random_pseudo_bytes(16);
        assert(strlen($data) == 16);

        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
<?php

use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumberToCarrierMapper;
  

function getCarrierName($phoneNumber, $region) {
    $phoneUtil = PhoneNumberUtil::getInstance();
    $carrierMapper = PhoneNumberToCarrierMapper::getInstance();

    try {
        $parsedNumber = $phoneUtil->parse($phoneNumber, $region);
        return $carrierMapper->getNameForNumber($parsedNumber, 'en');
    } catch (\libphonenumber\NumberParseException $e) {
        return 'Invalid_number';
    }
} 

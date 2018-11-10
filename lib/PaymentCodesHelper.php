<?php

/* 
/
//
function getResultDescription($responseCode) {

    switch ($responseCode) {
        case "0" : $result = "Transaction Successful"; break;
        case "?" : $result = "Transaction status is unknown"; break;
        case "E" : $result = "Referred"; break;
        case "1" : $result = "Transaction Declined"; break;
        case "2" : $result = "Bank Declined Transaction"; break;
        case "3" : $result = "No Reply from Bank"; break;
        case "4" : $result = "Expired Card"; break;
        case "5" : $result = "Insufficient funds"; break;
        case "6" : $result = "Error Communicating with Bank"; break;
        case "7" : $result = "Payment Server detected an error"; break;
        case "8" : $result = "Transaction Type Not Supported"; break;
        case "9" : $result = "Bank declined transaction (Do not contact Bank)"; break;
        case "A" : $result = "Transaction Aborted"; break;
        case "B" : $result = "Fraud Risk Blocked"; break;
		case "C" : $result = "Transaction Cancelled"; break;
        case "D" : $result = "Deferred transaction has been received and is awaiting processing"; break;
        case "E" : $result = "Transaction Declined - Refer to card issuer"; break;
		case "F" : $result = "3D Secure Authentication failed"; break;
        case "I" : $result = "Card Security Code verification failed"; break;
        case "L" : $result = "Shopping Transaction Locked (Please try the transaction again later)"; break;
        case "M" : $result = "Transaction Submitted (No response from acquirer)"; break;
		case "N" : $result = "Cardholder is not enrolled in Authentication scheme"; break;
        case "P" : $result = "Transaction has been received by the Payment Adaptor and is being processed"; break;
        case "R" : $result = "Transaction was not processed - Reached limit of retry attempts allowed"; break;
        case "S" : $result = "Duplicate SessionID (Amex Only)"; break;
        case "T" : $result = "Address Verification Failed"; break;
        case "U" : $result = "Card Security Code Failed"; break;
        case "V" : $result = "Address Verification and Card Security Code Failed"; break;
        default  : $result = "Unable to be determined"; 
    }
    return $result;
}

//  ----------------------------------------------------------------------------

// This function uses the QSI AVS Result Code retrieved from the Digital
// Receipt and returns an appropriate description for this code.

// @param avsResultCode String containing the QSI AVS Result Code
// @return description String containing the appropriate description

function getAVSResultDescription($avsResultCode) {
    
    if ($avsResultCode != "") { 
        switch ($avsResultCode) {
            Case "Unsupported" : $result = "AVS not supported or there was no AVS data provided"; break;
            Case "X"  : $result = "Exact match - address and 9 digit ZIP/postal code"; break;
            Case "Y"  : $result = "Exact match - address and 5 digit ZIP/postal code"; break;
            Case "W"  : $result = "9 digit ZIP/postal code matched, Address not Matched"; break;
			Case "S"  : $result = "Service not supported or address not verified (international transaction)"; break;
            Case "G"  : $result = "Issuer does not participate in AVS (international transaction)"; break;
			Case "C"  : $result = "Street Address and Postal Code not verified for International Transaction due to incompatible formats."; break;
            Case "I"  : $result = "Visa Only. Address information not verified for international transaction."; break;
			Case "A"  : $result = "Address match only"; break;
            Case "Z"  : $result = "5 digit ZIP/postal code matched, Address not Matched"; break;
            Case "R"  : $result = "Issuer system is unavailable"; break;
            Case "U"  : $result = "Address unavailable or not verified"; break;
            Case "E"  : $result = "Address and ZIP/postal code not provided"; break;
			Case "B"  : $result = "Street Address match for international transaction. Postal Code not verified due to incompatible formats."; break;
            Case "N"  : $result = "Address and ZIP/postal code not matched"; break;
            Case "0"  : $result = "AVS not requested"; break;
			Case "D"  : $result = "Street Address and postal code match for international transaction."; break;
			Case "M"  : $result = "Street Address and postal code match for international transaction."; break;
			Case "P"  : $result = "Postal Codes match for international transaction but street address not verified due to incompatible formats."; break;
			Case "K"  : $result = "Card holder name only matches."; break;
			Case "F"  : $result = "Street address and postal code match. Applies to U.K. only."; break;
            default   : $result = "Unable to be determined"; 
        }
    } else {
        $result = "null response";
    }
    return $result;
}

//  ----------------------------------------------------------------------------

// This function uses the QSI CSC Result Code retrieved from the Digital
// Receipt and returns an appropriate description for this code.

// @param cscResultCode String containing the QSI CSC Result Code
// @return description String containing the appropriate description

function getCSCResultDescription($cscResultCode) {
    
    if ($cscResultCode != "") {
        switch ($cscResultCode) {
            Case "Unsupported" : $result = "CSC not supported or there was no CSC data provided"; break;
            Case "M"  : $result = "Exact code match"; break;
            Case "S"  : $result = "Merchant has indicated that CSC is not present on the card (MOTO situation)"; break;
            Case "P"  : $result = "Code not processed"; break;
            Case "U"  : $result = "Card issuer is not registered and/or certified"; break;
            Case "N"  : $result = "Code invalid or not matched"; break;
            default   : $result = "Unable to be determined"; break;
        }
    } else {
        $result = "null response";
    }
    return $result;
}

//  -----------------------------------------------------------------------------

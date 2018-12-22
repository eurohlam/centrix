<?php
if (!defined('ABSPATH')) exit;

class Centrix_Integration {

  public function __construct() {
  }

  public function milliseconds() {
      date_default_timezone_set("Pacific/Auckland");
      $mt = explode(' ', microtime());
      return ((int)$mt[1]) * 1000 + ((int)round($mt[0] * 1000));
  }

  public function prepare_centrix_parameters($subscriberId, $userId, $userKey, $requestData) {
      $request = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:cen="http://centrix.co.nz">
   <soapenv:Header />
   <soapenv:Body>
      <cen:GetCreditReportProducts>
         <cen:CreditReportProductsRequest>
            <cen:Credentials>
               <cen:SubscriberID>' . $subscriberId .'</cen:SubscriberID>
               <cen:UserID>' . $userId . '</cen:UserID>
               <cen:UserKey>' . $userKey . '</cen:UserKey>
            </cen:Credentials>
            <cen:ServiceProducts>
               <cen:ServiceProduct>
                  <cen:ProductCode>SmartID</cen:ProductCode>
               </cen:ServiceProduct>
            </cen:ServiceProducts>
            <cen:RequestDetails>
               <cen:EnquiryReason>IDVF</cen:EnquiryReason>
               <cen:SubscriberReference />
            </cen:RequestDetails>';
            if (isset($requestData['details']['driverslicence']['number'])) {
                $request = $request .
                '<cen:DriverLicence>
                   <cen:DriverLicenceNumber>' . $requestData['details']['driverslicence']['number'] . '</cen:DriverLicenceNumber>
                   <cen:DriverLicenceVersion>' . $requestData['details']['driverslicence']['version'] . '</cen:DriverLicenceVersion>
                </cen:DriverLicence>';
            }
            $request = $request .
            '<cen:ConsumerData>
               <cen:Personal>
                  <cen:Surname>' . $requestData['details']['name']['family'] . '</cen:Surname>
                  <cen:FirstName>' . $requestData['details']['name']['given'] . '</cen:FirstName>
                  <cen:MiddleName>' . $requestData['details']['name']['middle'] . '</cen:MiddleName>
                  <cen:DateOfBirth>' . $requestData['details']['dateofbirth'] . '</cen:DateOfBirth>
               </cen:Personal>
               <cen:Addresses>
                  <cen:Address>
                     <cen:AddressType>' . $requestData['details']['address']['addresstype'] . '</cen:AddressType>
                     <cen:AddressLine1>' . $requestData['details']['address']['streetnumber'] . ' ' . $requestData['details']['address']['streetname'] . '</cen:AddressLine1>
                     <cen:AddressLine2 />
                     <cen:Suburb>' . $requestData['details']['address']['suburb'] . '</cen:Suburb>
                     <cen:City>' . $requestData['details']['address']['city'] . '</cen:City>
                     <cen:Country>NZL</cen:Country>
                     <cen:Postcode>' . $requestData['details']['address']['postcode'] . '</cen:Postcode>
                  </cen:Address>
               </cen:Addresses>';
               if (isset($requestData['details']['passport']['number'])) {
                   $request = $request .
                   '<cen:Passport>
                      <cen:PassportNumber>' . $requestData['details']['passport']['number'] . '</cen:PassportNumber>
                      <cen:Expiry>' . $requestData['details']['passport']['expiry'] . '</cen:Expiry>
                   </cen:Passport>';
               }
            $request = $request . '</cen:ConsumerData>
            <cen:Consents>
               <cen:Consent>
                  <cen:Name>DIAPassportVerification</cen:Name>
                  <cen:ConsentGiven>1</cen:ConsentGiven>
               </cen:Consent>
            </cen:Consents>
         </cen:CreditReportProductsRequest>
      </cen:GetCreditReportProducts>
   </soapenv:Body>
</soapenv:Envelope>';


      $resultMap = array(
          'key' => $accessKey,
          'nonce' => $nonce,
          'timestamp' => $timestamp,
          $dataKeyName => $data,
          'signature' => $signatureHex
      );

      return $request;
  }

  public function send_request($url, $httpUser, $httpPassword, $soapAction, $requestParams) {
      $path = '/v16/Consumers.svc'; //TODO: should be configurable?
      $curl = curl_init();
      $params = array(
              CURLOPT_RETURNTRANSFER => 1,
              CURLOPT_HTTPHEADER => array('Content-type: text/xml', 'SOAPAction: ' . $soapAction),
              CURLOPT_USERPWD => $httpUser . ":" . $httpPassword,
              CURLOPT_URL => $url . $path,
              CURLOPT_PORT => 443,
              CURLOPT_POST => 1, //method=POST
              CURLOPT_SSL_VERIFYHOST => false,
              CURLOPT_VERBOSE => 1,
              CURLOPT_POSTFIELDS => $requestParams
          );
      curl_setopt_array($curl, $params);
      $result = null;
      try {
          $result = curl_exec($curl);
          if (!$result) {
              $errno = curl_errno($curl);
              $error = curl_error($curl);
              error_log($error);
          }
          curl_close($curl);
      } catch (HttpException $ex) {
            error_log($ex);
      }
      return $result;
  }

  public function get_enquiry_number($getCreditReportProductsResponse) {
      $xml = new SimpleXMLElement($getCreditReportProductsResponse);
      $xml->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
      $xml->registerXPathNamespace('c', 'http://centrix.co.nz');
      $enquiryNumber = $xml->xpath('//soap:Envelope/soap:Body/c:GetCreditReportProductsResponse/c:GetCreditReportProductsResult/c:ResponseDetails/c:EnquiryNumber');
      return $enquiryNumber[0];
  }

  public function get_pdf($url, $subscriberId, $userId, $userKey, $enquiryNumber) {
      $path = '/Bureau/Secure2/CreditReports/' . $enquiryNumber . '.pdf?SubscriberID=' . $subscriberId . '&UserID=' . $userId . '&UserKey=' . urlencode($userKey);
      $curl = curl_init();
      $params = array(
              CURLOPT_RETURNTRANSFER => 1,
              CURLOPT_HTTPHEADER => array('Content-type: application/pdf'),
              CURLOPT_URL => $url . $path,
              CURLOPT_PORT => 443,
              CURLOPT_POST => 0, // method=GET
              CURLOPT_SSL_VERIFYHOST => false,
              CURLOPT_VERBOSE => 1
          );
      curl_setopt_array($curl, $params);
      $result = null;
      try {
          $result = curl_exec($curl);
          if (!$result) {
              $errno = curl_errno($curl);
              $error = curl_error($curl);
              error_log($error);
          }
          $result = $this->save_pdf_as_file($result);
          curl_close($curl);
      } catch (HttpException $ex) {
            error_log($ex);
      }
      return $result;
  }

  private function save_pdf_as_file ($file) {
      $upload = wp_upload_dir();
      $path = $upload['path'];
      $url = $upload['url'];
      if(file_exists($upload['basedir'] . '/centrix_int')) {
          $path = $upload['basedir'] . '/centrix_int';
          $url = $upload['baseurl'] . '/centrix_int';
      }
      //wp_mkdir_p($upload['basedir'] . '/cloudcheck');
      $filename = $this->milliseconds() . '.pdf';
      file_put_contents($path . '/' . $filename, $file);
      $result = '{"pdfUrl" : "' . $url . '/' . $filename .'",'.
                 '"pdfPath" : "' . $path . '/' . $filename . '"}';
      return $result;
  }

}

?>

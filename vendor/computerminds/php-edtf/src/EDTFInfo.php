<?php

namespace ComputerMinds\EDTF;

use GuzzleHttp;

/**
 * Validate and return basic information about EDTF date strings.
 *
 * Internally we use a web service to handle date parsing etc.
 *
 * @package ComputerMinds\EDTF
 */
class EDTFInfo implements EDTFInfoInterface {

  protected $dateString;

  protected $apiData;
  protected $apiDataJson;

  protected $valid = FALSE;
  protected $min;
  protected $max;

  /**
   * EDTFInfo constructor.
   *
   * @param $dateString
   *   The date string to return information about.
   * @param bool $lazy_load
   *   Specify if the date about the date should be lazy loaded.
   */
  public function __construct($dateString, $lazy_load = TRUE) {
    $this->dateString = $dateString;
    if (!$lazy_load) {
      $this->ensureAPIData();
    }
  }

  /**
   * Get the data about the date string from the API.
   */
  protected function ensureAPIData() {
    if (!isset($this->apiData)) {
      $client = new GuzzleHttp\Client();
      try {
        $res = $client->request('GET', 'http://edtf.herokuapp.com/', array(
          'query' => array(
            'date' => $this->dateString,
          ),
        ));
      }
      catch (GuzzleHttp\Exception\ServerException $guzzle_exception) {

      }
      catch (GuzzleHttp\Exception\ClientException $guzzle_exception) {

      }
      if (isset($res) && $res->getStatusCode() == 200) {
        $this->valid = TRUE;
        $this->apiData = $res->getBody();
        $this->apiDataJson = json_decode($this->apiData, TRUE);
        $min = $this->apiDataJson['min'];
        if (strpos($min, '-') === 0) {
          // JS adds some leading zeros to negative dates.
         $min = preg_replace('#^-00#', '-', $min);
        }
        $this->min = new \DateTime($min);

        $max = $this->apiDataJson['max'];
        if (strpos($max, '-') === 0) {
          // JS adds some leading zeros to negative dates.
          $max = preg_replace('#^-00#', '-', $max);
        }
        $this->max = new \DateTime($max);
      }
    }
  }

  /**
   * Return the validity of the EDTF date string.
   *
   * @return bool
   */
  public function isValid() {
    $this->ensureAPIData();
    return $this->valid;
  }

  /**
   * Get the earliest date that this instance could represent.
   *
   * @return \DateTime
   */
  public function getMin() {
    $this->ensureAPIData();
    return $this->min;

  }

  /**
   * Get the latest date that this instance could represent.
   *
   * @return \DateTime
   */
  public function getMax() {
    $this->ensureAPIData();
    return $this->max;
  }
}

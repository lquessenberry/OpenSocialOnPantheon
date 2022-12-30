<?php

namespace Drupal\typed_data;

/**
 * Helper for classes that need the data fetcher object.
 */
trait DataFetcherTrait {

  /**
   * The data fetcher.
   *
   * @var \Drupal\typed_data\DataFetcherInterface
   */
  protected $dataFetcher;

  /**
   * Sets the data fetcher.
   *
   * @param \Drupal\typed_data\DataFetcherInterface $data_fetcher
   *   The data fetcher.
   *
   * @return $this
   */
  public function setDataFetcher(DataFetcherInterface $data_fetcher) {
    $this->dataFetcher = $data_fetcher;
    return $this;
  }

  /**
   * Gets the data fetcher.
   *
   * @return \Drupal\typed_data\DataFetcherInterface
   *   The data fetcher.
   */
  public function getDataFetcher() {
    if (empty($this->dataFetcher)) {
      $this->dataFetcher = \Drupal::service('typed_data.data_fetcher');
    }

    return $this->dataFetcher;
  }

}

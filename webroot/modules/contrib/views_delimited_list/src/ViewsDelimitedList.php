<?php

namespace Drupal\views_delimited_list;

use Drupal\views\ViewExecutable;

/**
 * Logic for separator, delimiters, and conjunctives.
 */
class ViewsDelimitedList {

  /**
   * The view.
   *
   * @var \Drupal\views\ViewExecutable
   */
  protected $view;

  /**
   * The rows.
   *
   * @var array
   */
  protected $rows;

  /**
   * Count of the rows.
   *
   * @var int
   */
  protected $count;

  /**
   * The separator value.
   *
   * @var string
   */
  protected $separator;

  /**
   * Constructs ViewDelimitedList object.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   Views object.
   * @param array $rows
   *   The rows.
   */
  public function __construct(ViewExecutable $view, array $rows) {
    $this->view = $view;
    $this->rows = $rows;
    $this->count = count($rows);
    $this->separator = $this->getSeparator();
  }

  /**
   * Gets the separator value.
   *
   * @return string
   *   The separator value.
   */
  protected function getSeparator() {
    $options = $this->view->getStyle()->options;

    if ($this->count === 2 && $this->count != $options['long_count']) {
      $separator = $options['separator_two'];
    }
    else {
      $separator = $options['separator_long'];
    }

    return $separator;
  }

  /**
   * Check if this needs delimiter.
   *
   * @param int $row_index
   *   The index of the row.
   *
   * @return bool[]
   *   The has delimiter value.
   */
  protected function hasDelimiter($row_index) {
    $current_row_index = $row_index + 1;
    $is_second_last_item = ($current_row_index == $this->count - 1);
    $last_delimiter = in_array($this->separator, ['both', 'delimiter'], TRUE);
    if ($current_row_index < $this->count - 1 || ($is_second_last_item && $last_delimiter)) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Gets the delimiters.
   *
   * @return bool[]
   *   The delimiter setting for the rows.
   */
  public function getDelimiters() {
    $delimiters = [];
    foreach ($this->rows as $row_index => $row) {
      $delimiters[$row_index] = $this->hasDelimiter($row_index);
    }
    return $delimiters;
  }

  /**
   * Gets the conjunctives.
   *
   * @return bool[]
   *   The conjunctive setting for the rows.
   */
  public function getConjunctives() {
    $last_conjunctive = in_array($this->separator, ['both', 'conjunctive'], TRUE);

    $conjunctives = [];
    foreach ($this->rows as $row_index => $row) {
      $current_row_index = $row_index + 1;
      $is_second_last_item = ($current_row_index == $this->count - 1);
      $has_conjunctive = FALSE;
      if ($is_second_last_item && $last_conjunctive) {
        $has_conjunctive = TRUE;
      }

      $conjunctives[$row_index] = $has_conjunctive;
    }
    return $conjunctives;
  }

}

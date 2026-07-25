<?php

namespace KateMorley\Grid\State;

/** A data point. */
class Datum {
  public readonly float   $price;
  public readonly float   $emissions;
  public readonly Sources $sources;
  public readonly float   $visits;

  /**
   * Constructs a new instance.
   *
   * @param array $map The map of data
   */
  public function __construct(array $map) {
    $this->price     = $map['price'];
    $this->emissions = $map['emissions'];
    $this->sources   = new Sources($map);
    $this->visits    = $map['visits'];
  }
}

<?php

namespace KateMorley\Grid\State;

/** Represents details of storage. */
class Storage extends Map {
  public const PUMPED = 'pumped';

  public const KEYS = [
    self::PUMPED => 'Pumped'
  ];

  protected const KEY_COMPONENTS = [
    self::PUMPED => ['pumped']
  ];
}

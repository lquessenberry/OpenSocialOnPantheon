<?php

namespace Drupal\image_effects\Component;

/**
 * Rectangle algebra class.
 */
class PositionedRectangle {

  /**
   * An array of point coordinates, keyed by an id.
   *
   * Canonical points are:
   * 'c_a' - bottom left corner of the rectangle
   * 'c_b' - bottom right corner of the rectangle
   * 'c_c' - top right corner of the rectangle
   * 'c_d' - top left corner of the rectangle
   * 'o_a' - bottom left corner of the bounding rectangle, once the rectangle
   *         is rotated
   * 'o_c' - top right corner of the bounding rectangle, once the rectangle
   *         is rotated
   * Additional points can be added through the setPoint() method. These will
   * be subject to translation/rotation with the rest of the points when
   * getTranslatedRectangle() method is executed.
   *
   * @var array
   */
  protected $points = [];

  /**
   * The width of the rectangle.
   *
   * The width is not influenced by rotation/translation.
   *
   * @var int
   */
  protected $width = 0;

  /**
   * The height of the rectangle.
   *
   * The height is not influenced by rotation/translation.
   *
   * @var int
   */
  protected $height = 0;

  /**
   * The angle at which the rectangle has been rotated.
   *
   * @var float
   */
  protected $angle = 0;

  /**
   * The offset needed to reposition the rectangle fully into first quadrant.
   *
   * Rotating a rectangle which is sticking to axes in the first quadrant
   * results in some of its corners to shift to other quadrants. The x/y
   * offset required to reposition it fully in the first quadrant is stored
   * here.
   *
   * @var array
   */
  protected $rotationOffset = [0, 0];

  /**
   * Constructs a new PositionedRectangle object.
   *
   * @param int $width
   *   (Optional) The width of the rectangle.
   * @param int $height
   *   (Optional) The height of the rectangle.
   */
  public function __construct($width = 0, $height = 0) {
    if ($width !== 0 && $height !== 0) {
      $this->setFromDimensions($width, $height);
    }
  }

  /**
   * Sets a rectangle from its width and height.
   *
   * @param int $width
   *   The width of the rectangle.
   * @param int $height
   *   The height of the rectangle.
   *
   * @return $this
   */
  public function setFromDimensions($width, $height) {
    $this->setFromCorners([
      'c_a' => [0, 0],
      'c_b' => [$width - 1, 0],
      'c_c' => [$width - 1, $height - 1],
      'c_d' => [0, $height - 1],
    ]);
    return $this;
  }

  /**
   * Sets a rectangle from the coordinates of its corners.
   *
   * @param array $corners
   *   An associative array of point coordinates. The keys 'c_a', 'c_b',
   *   'c_c' and 'c_d' represent each of the four a, b, c, d corners of the
   *   rectangle in the format
   *   D +-----------------+ C
   *     |                 |
   *     |                 |
   *   A +-----------------+ B.
   *
   * @return $this
   */
  public function setFromCorners(array $corners) {
    $this
      ->setPoint('c_a', $corners['c_a'])
      ->setPoint('c_b', $corners['c_b'])
      ->setPoint('c_c', $corners['c_c'])
      ->setPoint('c_d', $corners['c_d'])
      ->determineBoundingCorners();
    $this->width = $this->getBoundingWidth();
    $this->height = $this->getBoundingHeight();
    return $this;
  }

  /**
   * Sets a point and its coordinates.
   *
   * @param string $id
   *   The point ID.
   * @param array $coords
   *   An array of x, y coordinates.
   *
   * @return $this
   */
  public function setPoint($id, array $coords = [0, 0]) {
    $this->points[$id] = $coords;
    return $this;
  }

  /**
   * Gets the coordinates of a point.
   *
   * @param string $id
   *   The point ID.
   *
   * @return array
   *   An array of x, y coordinates.
   */
  public function getPoint($id) {
    return $this->points[$id];
  }

  /**
   * Gets the width of the rectangle.
   *
   * @return int
   *   The width of the rectangle.
   */
  public function getWidth() {
    return $this->width;
  }

  /**
   * Gets the height of the rectangle.
   *
   * @return int
   *   The height of the rectangle.
   */
  public function getHeight() {
    return $this->height;
  }

  /**
   * Gets the rotation offset of the rectangle.
   *
   * @return array
   *   The x/y offset required to reposition the rectangle fully in the first
   *   quadrant after it has been rotated.
   */
  public function getRotationOffset() {
    return $this->rotationOffset;
  }

  /**
   * Gets the bounding width of the rectangle.
   *
   * @return int
   *   The bounding width of the rotated rectangle.
   */
  public function getBoundingWidth() {
    return $this->points['o_c'][0] - $this->points['o_a'][0] + 1;
  }

  /**
   * Gets the bounding height of the rectangle.
   *
   * @return int
   *   The bounding height of the rotated rectangle.
   */
  public function getBoundingHeight() {
    return $this->points['o_c'][1] - $this->points['o_a'][1] + 1;
  }

  /**
   * Translates a point by an offset.
   *
   * @param array $point
   *   An array of x, y coordinates.
   * @param array $offset
   *   Offset array (x, y).
   *
   * @return $this
   */
  protected function translatePoint(array &$point, array $offset) {
    $point[0] += $offset[0];
    $point[1] += $offset[1];
    return $this;
  }

  /**
   * Rotates a point, by a rotation angle.
   *
   * @param array $point
   *   An array of x, y coordinates.
   * @param float $angle
   *   Rotation angle.
   *
   * @return $this
   */
  protected function rotatePoint(array &$point, $angle) {
    $rad = deg2rad($angle);
    $sin = sin($rad);
    $cos = cos($rad);
    list($x, $y) = $point;
    $tx = round(($x * $cos + $y * -$sin), 3);
    $ty = round(($y * $cos - $x * -$sin), 3);
    $point[0] = ($tx >= 0) ? ceil($tx) : -ceil(-$tx);
    $point[1] = ($ty >= 0) ? ceil($ty) : -ceil(-$ty);
    return $this;
  }

  /**
   * Rotates the rectangle and any additional point.
   *
   * @param float $angle
   *   Rotation angle.
   */
  public function rotate($angle) {
    if ($angle) {
      $this->angle = $angle;
      foreach ($this->points as &$point) {
        $this->rotatePoint($point, $angle);
      }
      $this->determineBoundingCorners();
      $this->rotationOffset = [-$this->points['o_a'][0], -$this->points['o_a'][1]];
    }
    return $this;
  }

  /**
   * Translates the rectangle and any additional point.
   *
   * @param array $offset
   *   Offset array (x, y).
   *
   * @return $this
   */
  public function translate(array $offset) {
    if ($offset[0] || $offset[1]) {
      foreach ($this->points as &$point) {
        $this->translatePoint($point, $offset);
      }
    }
    return $this;
  }

  /**
   * Calculates the corners of the bounding rectangle.
   *
   * The bottom left ('o_a') and top right ('o_c') corners of the bounding
   * rectangle of a rotated rectangle are needed to determine the bounding
   * width and height, and to calculate rotation-induced offest.
   *
   * @return $this
   */
  protected function determineBoundingCorners() {
    $this
      ->setPoint('o_a', [
        min($this->points['c_a'][0], $this->points['c_b'][0], $this->points['c_c'][0], $this->points['c_d'][0]),
        min($this->points['c_a'][1], $this->points['c_b'][1], $this->points['c_c'][1], $this->points['c_d'][1]),
      ])
      ->setPoint('o_c', [
        max($this->points['c_a'][0], $this->points['c_b'][0], $this->points['c_c'][0], $this->points['c_d'][0]),
        max($this->points['c_a'][1], $this->points['c_b'][1], $this->points['c_c'][1], $this->points['c_d'][1]),
      ]);
    return $this;
  }

}

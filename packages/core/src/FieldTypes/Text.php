<?php

namespace GetCandy\FieldTypes;

use GetCandy\Base\FieldType;
use GetCandy\Exceptions\FieldTypeException;
use JsonSerializable;

class Text implements FieldType, JsonSerializable
{
    /**
     * @var string
     */
    protected $value;

    /**
     * Serialize the class.
     *
     * @return string
     */
    public function jsonSerialize()
    {
        return $this->value;
    }

    /**
     * Create a new instance of Text field type.
     *
     * @param string $value
     */
    public function __construct($value = '')
    {
        $this->setValue($value);
    }

    /**
     * Returns the value when accessed as a string.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->getValue();
    }

    /**
     * Return the value of this field.
     *
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Set the value of this field.
     *
     * @param string $value
     */
    public function setValue($value)
    {
        if ($value && !is_string($value)) {
            throw new FieldTypeException(self::class.' value must be a string.');
        }

        $this->value = $value;
    }
}

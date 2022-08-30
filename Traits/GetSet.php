<?php

namespace FFFlabel\Services\Traits;

use FFFlabel\Services\Helpers\TextHelpers;

trait GetSet {

	/**
	 * Global setters for all public fields of class
	 *
	 * @param $name  - name of field
	 * @param $value - value of field
	 * @return $this - return object of class
	 */
	public function set($name, $value)
	{
		if (property_exists($this, $name)) {

			if (method_exists($this,'set' . TextHelpers::camelcase($name))) {
				$this->{'set' . TextHelpers::camelcase($name)}($value);
			} else {
				$this->{$name} = $value;
			}
		}
		return $this;
	}

	/**
	 * Global getters for all public fields of class
	 *
	 * @param $name  - name of field
	 * @return mixed - value of the field $name or false if not exist
	 */
	public function get($name)
	{
		if (isset($this->{$name})) {

			if (method_exists($this,'get' . TextHelpers::camelcase($name))) {
				return $this->{'get' . TextHelpers::camelcase($name)}();
			} else {
				return $this->{$name};
			}

		}

		return false;
	}

	/**
	 * Set values for fields from array
	 *
	 * @param array $array  - array where keys it is names of fields and values it is values of fields
	 * @return $this
	 */
	public function updateFromArray(array $array)
	{
		if (!empty($array)) {
			foreach ($array as $key=>$item) {
				$this->set($key,$item);
			}
		}

		return $this;
	}

}
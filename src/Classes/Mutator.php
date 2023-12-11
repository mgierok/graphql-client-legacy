<?php

namespace MGierok\GraphqlClient\Classes;

use BadMethodCallException;
use Illuminate\Support\Str;

class Mutator {

    public function __get($key)
    {
        if(method_exists($this, 'get'.Str::studly($key).'Attribute')) {
            return $this->{'get'.Str::studly($key).'Attribute'}();
        }
        elseif (array_key_exists($key, $this->attributes)) {
            return $this->attributes[$key];
        }
    }

    public function __call($method, $arguments = [])
    {
        if (Str::startsWith($method, 'with') && property_exists($this, Str::camel(substr($method, 4)))) {
            // Set property, if exits, using withProperty naming
            $this->{Str::camel(substr($method, 4))} = $arguments[0];
        }
        elseif (Str::startsWith($method, 'with')) {
            // Set attribute using withAttribute naming
            $this->variables[Str::camel(substr($method, 4))] = $arguments[0];
        }
        else {
            throw new BadMethodCallException("Method [$method] does not exist.");
        }

        return $this;
    }
}

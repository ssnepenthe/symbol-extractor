<?php

namespace AnonymousClasses;

$o = new class {};

class Alfa {}

class Bravo {
    public function charlie()
    {
        return new class {};
    }
}
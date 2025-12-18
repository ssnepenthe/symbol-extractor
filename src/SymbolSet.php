<?php

namespace SymbolExtractor;

final class SymbolSet
{
    private array $class = [];
    private array $enum = [];
    private array $function = [];
    private array $interface = [];
    private array $trait = [];

    public function addClass(string $class)
    {
        $this->class[$class] = true;
    }

    public function getClasses()
    {
        return array_keys($this->class);
    }

    public function addEnum(string $enum)
    {
        $this->enum[$enum] = true;
    }

    public function getEnums()
    {
        return array_keys($this->enum);
    }

    public function addFunction(string $function)
    {
        $this->function[$function] = true;
    }

    public function getFunctions()
    {
        return array_keys($this->function);
    }

    public function addInterface(string $interface)
    {
        $this->interface[$interface] = true;
    }

    public function getInterfaces()
    {
        return array_keys($this->interface);
    }

    public function addTrait(string $trait)
    {
        $this->trait[$trait] = true;
    }

    public function getTraits()
    {
        return array_keys($this->trait);
    }

    public function getAll()
    {
        return [
            'class' => array_keys($this->class),
            'enum' => array_keys($this->enum),
            'function' => array_keys($this->function),
            'interface' => array_keys($this->interface),
            'trait' => array_keys($this->trait),
        ];
    }
}
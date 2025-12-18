<?php

namespace AttributeBeforeDeclaration;

#[Route('/api')]
class Alfa {}

#[Route('/api')]
#[Entity]
class Bravo {}
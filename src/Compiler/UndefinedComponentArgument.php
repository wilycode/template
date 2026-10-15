<?php

declare(strict_types=1);

namespace WilyCode\Template\Compiler;

/** @internal Sentinel used to distinguish an omitted untyped default from null. */
enum UndefinedComponentArgument
{
    case Value;
}

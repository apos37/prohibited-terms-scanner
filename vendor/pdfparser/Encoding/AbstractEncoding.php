<?php

namespace PTScannerVendor\Smalot\PdfParser\Encoding;

abstract class AbstractEncoding
{
    public abstract function getTranslations() : array;
}

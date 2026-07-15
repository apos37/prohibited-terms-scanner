<?php

/**
 * @file    This file is part of the PdfParser library.
 *
 * @author  Brian Huisman <bhuisman@greywyvern.com>
 *
 * @date    2023-06-28
 *
 * @license LGPLv3
 *
 * @url     <https://github.com/smalot/pdfparser>
 *
 *  PdfParser is a pdf library written in PHP, extraction oriented.
 *  Copyright (C) 2017 - Sébastien MALOT <sebastien@malot.fr>
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Lesser General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Lesser General Public License for more details.
 *
 *  You should have received a copy of the GNU Lesser General Public License
 *  along with this program.
 *  If not, see <http://www.pdfparser.org/sites/default/LICENSE.txt>.
 */
// Source : https://opensource.adobe.com/dc-acrobat-sdk-docs/pdfstandards/pdfreference1.2.pdf
// Source : https://ia801001.us.archive.org/1/items/pdf1.7/pdf_reference_1-7.pdf
namespace PTScannerVendor\Smalot\PdfParser\Encoding;

/**
 * Class PDFDocEncoding
 */
class PDFDocEncoding
{
    public static function getCodePage() : array
    {
        return [
            "\x18" => "˘",
            // breve
            "\x19" => "ˇ",
            // caron
            "\x1a" => "ˆ",
            // circumflex
            "\x1b" => "˙",
            // dotaccent
            "\x1c" => "˝",
            // hungarumlaut
            "\x1d" => "˛",
            // ogonek
            "\x1e" => "˞",
            // ring
            "\x1f" => "˜",
            // tilde
            "" => '',
            "\x80" => "•",
            // bullet
            "\x81" => "†",
            // dagger
            "\x82" => "‡",
            // daggerdbl
            "\x83" => "…",
            // ellipsis
            "\x84" => "—",
            // emdash
            "\x85" => "–",
            // endash
            "\x86" => "ƒ",
            // florin
            "\x87" => "⁄",
            // fraction
            "\x88" => "‹",
            // guilsinglleft
            "\x89" => "›",
            // guilsinglright
            "\x8a" => "−",
            // minus
            "\x8b" => "‰",
            // perthousand
            "\x8c" => "„",
            // quotedblbase
            "\x8d" => "“",
            // quotedblleft
            "\x8e" => "”",
            // quotedblright
            "\x8f" => "‘",
            // quoteleft
            "\x90" => "’",
            // quoteright
            "\x91" => "‚",
            // quotesinglbase
            "\x92" => "™",
            // trademark
            "\x93" => "ﬁ",
            // fi
            "\x94" => "ﬂ",
            // fl
            "\x95" => "Ł",
            // Lslash
            "\x96" => "Œ",
            // OE
            "\x97" => "Š",
            // Scaron
            "\x98" => "Ÿ",
            // Ydieresis
            "\x99" => "Ž",
            // Zcaron
            "\x9a" => "ı",
            // dotlessi
            "\x9b" => "ł",
            // lslash
            "\x9c" => "œ",
            // oe
            "\x9d" => "š",
            // scaron
            "\x9e" => "ž",
            // zcaron
            "\x9f" => '',
            "\xa0" => "€",
            // Euro
            "\xa1" => "¡",
            // exclamdown
            "\xa2" => "¢",
            // cent
            "\xa3" => "£",
            // sterling
            "\xa4" => "¤",
            // currency
            "\xa5" => "¥",
            // yen
            "\xa6" => "¦",
            // brokenbar
            "\xa7" => "§",
            // section
            "\xa8" => "¨",
            // dieresis
            "\xa9" => "©",
            // copyright
            "\xaa" => "ª",
            // ordfeminine
            "\xab" => "«",
            // guillemotleft
            "\xac" => "¬",
            // logicalnot
            "\xad" => '',
            "\xae" => "®",
            // registered
            "\xaf" => "¯",
            // macron
            "\xb0" => "°",
            // degree
            "\xb1" => "±",
            // plusminus
            "\xb2" => "²",
            // twosuperior
            "\xb3" => "³",
            // threesuperior
            "\xb4" => "´",
            // acute
            "\xb5" => "µ",
            // mu
            "\xb6" => "¶",
            // paragraph
            "\xb7" => "·",
            // periodcentered
            "\xb8" => "¸",
            // cedilla
            "\xb9" => "¹",
            // onesuperior
            "\xba" => "º",
            // ordmasculine
            "\xbb" => "»",
            // guillemotright
            "\xbc" => "¼",
            // onequarter
            "\xbd" => "½",
            // onehalf
            "\xbe" => "¾",
            // threequarters
            "\xbf" => "¿",
            // questiondown
            "\xc0" => "À",
            // Agrave
            "\xc1" => "Á",
            // Aacute
            "\xc2" => "Â",
            // Acircumflex
            "\xc3" => "Ã",
            // Atilde
            "\xc4" => "Ä",
            // Adieresis
            "\xc5" => "Å",
            // Aring
            "\xc6" => "Æ",
            // AE
            "\xc7" => "Ç",
            // Ccedill
            "\xc8" => "È",
            // Egrave
            "\xc9" => "É",
            // Eacute
            "\xca" => "Ê",
            // Ecircumflex
            "\xcb" => "Ë",
            // Edieresis
            "\xcc" => "Ì",
            // Igrave
            "\xcd" => "Í",
            // Iacute
            "\xce" => "Î",
            // Icircumflex
            "\xcf" => "Ï",
            // Idieresis
            "\xd0" => "Ð",
            // Eth
            "\xd1" => "Ñ",
            // Ntilde
            "\xd2" => "Ò",
            // Ograve
            "\xd3" => "Ó",
            // Oacute
            "\xd4" => "Ô",
            // Ocircumflex
            "\xd5" => "Õ",
            // Otilde
            "\xd6" => "Ö",
            // Odieresis
            "\xd7" => "×",
            // multiply
            "\xd8" => "Ø",
            // Oslash
            "\xd9" => "Ù",
            // Ugrave
            "\xda" => "Ú",
            // Uacute
            "\xdb" => "Û",
            // Ucircumflex
            "\xdc" => "Ü",
            // Udieresis
            "\xdd" => "Ý",
            // Yacute
            "\xde" => "Þ",
            // Thorn
            "\xdf" => "ß",
            // germandbls
            "\xe0" => "à",
            // agrave
            "\xe1" => "á",
            // aacute
            "\xe2" => "â",
            // acircumflex
            "\xe3" => "ã",
            // atilde
            "\xe4" => "ä",
            // adieresis
            "\xe5" => "å",
            // aring
            "\xe6" => "æ",
            // ae
            "\xe7" => "ç",
            // ccedilla
            "\xe8" => "è",
            // egrave
            "\xe9" => "é",
            // eacute
            "\xea" => "ê",
            // ecircumflex
            "\xeb" => "ë",
            // edieresis
            "\xec" => "ì",
            // igrave
            "\xed" => "í",
            // iacute
            "\xee" => "î",
            // icircumflex
            "\xef" => "ï",
            // idieresis
            "\xf0" => "ð",
            // eth
            "\xf1" => "ñ",
            // ntilde
            "\xf2" => "ò",
            // ograve
            "\xf3" => "ó",
            // oacute
            "\xf4" => "ô",
            // ocircumflex
            "\xf5" => "õ",
            // otilde
            "\xf6" => "ö",
            // odieresis
            "\xf7" => "÷",
            // divide
            "\xf8" => "ø",
            // oslash
            "\xf9" => "ù",
            // ugrave
            "\xfa" => "ú",
            // uacute
            "\xfb" => "û",
            // ucircumflex
            "\xfc" => "ü",
            // udieresis
            "\xfd" => "ý",
            // yacute
            "\xfe" => "þ",
            // thorn
            "\xff" => "ÿ",
        ];
    }
    public static function convertPDFDoc2UTF8(string $content) : string
    {
        return \strtr($content, static::getCodePage());
    }
}

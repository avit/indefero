<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
# This file is part of InDefero, an open source project management application.
# Copyright (C) 2008 Céondo Ltd and contributors.
#
# InDefero is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
#
# InDefero is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
#
# ***** END LICENSE BLOCK ***** */

/**
 * Diff parser.
 *
 */
class IDF_Diff
{
    public $repo = '';
    public $diff = '';
    protected $lines = array();

    public $files = array();

    public function __construct($diff, $repo='')
    {
        $this->repo = $repo;
        $this->diff = $diff;
        $this->lines = preg_split("/\015\012|\015|\012/", $diff);
    }

    public function parse()
    {
        $current_file = '';
        $current_chunk = 0;
        $lline = 0;
        $rline = 0;
        $files = array();
        foreach ($this->lines as $line) {
            if (0 === strpos($line, 'diff --git a')) {
                $current_file = self::getFile($line);
                $files[$current_file] = array();
                $files[$current_file]['chunks'] = array();
                $files[$current_file]['chunks_def'] = array();
                $current_chunk = 0;
                continue;
            }
            if (0 === strpos($line, '@@ ')) {
                $files[$current_file]['chunks_def'][] = self::getChunk($line);
                $files[$current_file]['chunks'][] = array();
                $current_chunk++;
                $lline = $files[$current_file]['chunks_def'][$current_chunk-1][0][0];
                $rline = $files[$current_file]['chunks_def'][$current_chunk-1][1][0];
                continue;
            }
            if (0 === strpos($line, '---') or 0 === strpos($line, '+++')) {
                continue;
            }
            if (0 === strpos($line, '-')) {
                $files[$current_file]['chunks'][$current_chunk-1][] = array($lline, '', substr($line, 1));
                $lline++;
                continue;
            }
            if (0 === strpos($line, '+')) {
                $files[$current_file]['chunks'][$current_chunk-1][] = array('', $rline, substr($line, 1));
                $rline++;
                continue;
            }
            if (0 === strpos($line, ' ')) {
                $files[$current_file]['chunks'][$current_chunk-1][] = array($lline, $rline, substr($line, 1));
                $rline++;
                $lline++;
                continue;
            }
        }
        $this->files = $files;
        return $files;
    }

    public static function getFile($line)
    {
        $line = substr(trim($line), 10);
        $n = (int) strlen($line)/2;
        return trim(substr($line, 3, $n-3));
    }

    /**
     * Return the html version of a parsed diff.
     */
    public function as_html()
    {
        $out = '';
        foreach ($this->files as $filename=>$file) {
            $out .= "\n".'<table class="diff" summary="">'."\n";
            $out .= '<tr id="diff-'.md5($filename).'"><th colspan="3">'.Pluf_esc($filename).'</th></tr>'."\n";
            $cc = 1;
            foreach ($file['chunks'] as $chunk) {
                foreach ($chunk as $line) {
                    if ($line[0] and $line[1]) {
                        $class = 'diff-c';
                    } elseif ($line[0]) {
                        $class = 'diff-r';
                    } else {
                        $class = 'diff-a';
                    }
                    $line_content = $this->padLine(Pluf_esc($line[2]));
                    $out .= sprintf('<tr class="diff-line"><td class="diff-lc">%s</td><td class="diff-lc">%s</td><td class="%s mono">%s</td></tr>'."\n", $line[0], $line[1], $class, $line_content);
                }
                if (count($file['chunks']) > $cc)
                $out .= '<tr class="diff-next"><td>...</td><td>...</td><td>&nbsp;</td></tr>'."\n";
                $cc++;
            }
            $out .= '</table>';
        }
        return $out;
    }


    public function padLine($line)
    {
        $n = strlen($line);
        for ($i=0;$i<$n;$i++) {
            if (substr($line, $i, 1) != ' ') {
                break;
            }
        }
        return str_repeat('&nbsp;', $i).substr($line, $i);
    }

    /**
     * @return array array(array(start, n), array(start, n))
     */
    public static function getChunk($line)
    {
        $elts = split(' ', $line);
        $res = array();
        for ($i=1;$i<3;$i++) {
            $res[] = split(',', trim(substr($elts[$i], 1)));
        }
        return $res;
    }

}
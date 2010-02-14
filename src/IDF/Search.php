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
# Based on work under GNU LGPL copyright, from the Pluf Framework
# Copyright (C) 2001-2007 Loic d'Anterroches and contributors.
#
# ***** END LICENSE BLOCK ***** */

/**
 * Class implementing the search engine
 *
 * It is a modified version of the Pluf_Search class to be able to
 * cluster the results by project.
 */
class IDF_Search extends Pluf_Search
{
    /**
     * Search.
     *
     * Returns an array of array with model_class, model_id and
     * score. The list is already sorted by score descending.
     *
     * You can then filter the list as you wish with another set of
     * weights.
     *
     * @param string Query string.
     * @param int Project id to limit the results (null)
     * @param string Model class (null)
     * @param string Stemmer class ('Pluf_Text_Stemmer_Porter')
     * @return array Results
     */
    public static function mySearch($query, $project=null, $model=null, $stemmer='Pluf_Text_Stemmer_Porter')
    {
        $query = Pluf_Text::cleanString(html_entity_decode($query, ENT_QUOTES, 'UTF-8'));
        $words = Pluf_Text::tokenize($query);
        if ($stemmer != null) {
            $words = self::stem($words, $stemmer);
        }
        $words_flat = array();
        foreach ($words as $word=>$c) {
            $words_flat[] = $word;
        }
        $word_ids = self::getWordIds($words_flat);
        if (in_array(null, $word_ids) or count($word_ids) == 0) {
            return array();
        }
        return self::mySearchDocuments($word_ids, $project, $model);
    }

    /**
     * Search documents.
     *
     * Only the total of the ponderated occurences is used to sort the
     * results.
     *
     * @param array Ids.
     * @param IDF_Project Project to limit the search.
     * @param string Model class to limit the search.
     * @return array Sorted by score, returns model_class, model_id and score.
     */
    public static function mySearchDocuments($wids, $project, $model)
    {
        $db =& Pluf::db();
        $gocc = new IDF_Search_Occ();
        $where = array();
        foreach ($wids as $id) {
            $where[] = $db->qn('word').'='.(int)$id;
        }
        $prj = (is_null($project)) ? '' : ' AND project='.(int)$project->id;
        $md = (is_null($model)) ? '' : ' AND model_class='.$db->esc($model);
        $select = 'SELECT model_class, model_id, SUM(pondocc) AS score FROM '.$gocc->getSqlTable().' WHERE '.implode(' OR ', $where).$prj.$md.' GROUP BY model_class, model_id HAVING COUNT(*)='.count($wids).' ORDER BY score DESC';
        return $db->select($select);
    }

    /**
     * Index a document.
     *
     * See Pluf_Search for the disclaimer and informations.
     *
     * @param Pluf_Model Document to index.
     * @param Stemmer used. ('Pluf_Text_Stemmer_Porter')
     * @return array Statistics.
     */
    public static function index($doc, $stemmer='Pluf_Text_Stemmer_Porter')
    {
        $words = Pluf_Text::tokenize($doc->_toIndex());
        if ($stemmer != null) {
            $words = self::stem($words, $stemmer);
        }
        // Get the total number of words.
        $total = 0.0;
        $words_flat = array();
        foreach ($words as $word => $occ) {
            $total += (float) $occ;
            $words_flat[] = $word;
        }
        // Drop the last indexation.
        $gocc = new IDF_Search_Occ();
        $sql = new Pluf_SQL('DELETE FROM '.$gocc->getSqlTable().' WHERE model_class=%s AND model_id=%s', array($doc->_model, $doc->id));
        $db =& Pluf::db();
        $db->execute($sql->gen());
        // Get the ids for each word.
        $ids = self::getWordIds($words_flat);
        // Insert a new word for the missing words and add the occ.
        $n = count($ids);
        $new_words = 0;
        $done = array();
        for ($i=0;$i<$n;$i++) {
            if ($ids[$i] === null) {
                $word = new Pluf_Search_Word();
                $word->word = $words_flat[$i];
                try {
                    $word->create();
                    $new_words++;
                    $ids[$i] = $word->id;
                } catch (Exception $e) {
                    // 100% of the time, the word has been created
                    // by another process in the background.
                    $r_ids = self::getWordIds(array($word->word));
                    if ($r_ids[0]) {
                        $ids[$i] = $r_ids[0];
                    } else {
                        // give up for this word
                        continue;
                    }
                }
            }
            if (isset($done[$ids[$i]])) {
                continue;
            }
            $done[$ids[$i]] = true;
            $occ = new IDF_Search_Occ();
            $occ->word = new Pluf_Search_Word($ids[$i]);
            $occ->model_class = $doc->_model;
            $occ->model_id = $doc->id;
            $occ->project = $doc->get_project();
            $occ->occ = $words[$words_flat[$i]];
            $occ->pondocc = $words[$words_flat[$i]]/$total;
            $occ->create();
        }
        // update the stats
        $sql = new Pluf_SQL('model_class=%s AND model_id=%s',
                            array($doc->_model, $doc->id));
        $last_index = Pluf::factory('Pluf_Search_Stats')->getList(array('filter' => $sql->gen()));
        if ($last_index->count() == 0) {
            $stats = new Pluf_Search_Stats();
            $stats->model_class = $doc->_model;
            $stats->model_id = $doc->id;
            $stats->indexations = 1;
            $stats->create();
        } else {
            $last_index[0]->indexations += 1;
            $last_index[0]->update();
        }
        return array('total' => $total, 'new' => $new_words, 'unique'=>$n);
    }

    /**
     * Remove an item from the index.
     *
     * You must call this function when you delete items wich are
     * indexed. Just add the call:
     *
     * IDF_Search::remove($this);
     *
     * in the preDelete() method of your object.
     *
     * @param mixed Item to be removed
     * @return bool Success
     */
    public static function remove($item)
    {
        if ($item->id > 0) {
            $sql = new Pluf_SQL('model_id=%s AND model_class=%s',
                                array($item->id, $item->_model));
            $items = Pluf::factory('IDF_Search_Occ')->getList(array('filter'=>$sql->gen()));
            foreach ($items as $tl) {
                $tl->delete();
            }
        }
        return true;
    }

}
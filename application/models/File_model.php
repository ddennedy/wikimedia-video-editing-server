<?php defined('BASEPATH') OR exit('No direct script access allowed');
/*
 * Wikimedia Video Editing Server
 * Copyright (C) 2014 Dan R. Dennedy <dan@dennedy.org>
 * Copyright (C) 2014 CDC Leuphana University Lueneburg
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

class File_model extends CI_Model
{
    const STATUS_UPLOADED   = 1;
    const STATUS_VALIDATED  = 2;
    const STATUS_CONVERTING = 4;
    const STATUS_FINISHED   = 8;
    const STATUS_APPROVED   = 16;
    const STATUS_REJECTED   = 32;
    const STATUS_PUBLISHED  = 64;
    const STATUS_ERROR      = 2147483648;

    public function __construct()
    {
        $this->load->database();
        log_message('debug', 'File Model Class Initialized');
    }

    public function getById($id = null)
    {
        if ($id) {
            $this->db->select('file.*, user.name as username');
            $this->db->join('user', 'user_id = user.id');
            $query = $this->db->get_where('file', ['file.id' => $id]);
            $data = $query->row_array();
        } else {
            $data = [
                'id' => null,
                'title' => null,
                'author' => null,
                'description' => null,
                'keywords' => null,
                'properties' => null,
                'recording_date' => null,
                'language' => null,
                'license' => null,
                'source_path' => null,
                'size_bytes' => null
            ];
        }
        return $data;
    }

    public function create($data)
    {
        // Insert into main file table.
        $this->db->trans_start();
        $result = $this->db->insert('file', $data);
        if ($result) {
            $id = $this->db->insert_id();

            // Add to recent table.
            $this->updateRecent($id);

            // Add keywords.
            $keywords = explode("\t", $data['keywords']);
            if (count($keywords)) {
                foreach ($keywords as $value) {
                    // keyword table
                    $entry = [
                        'value' => $value,
                        'language' => $this->config->item('language')
                    ];
                    $sql = $this->db->set($entry)->get_compiled_insert('keyword');
                    $sql .= ' ON DUPLICATE KEY UPDATE id=id';
                    $this->db->simple_query($sql);

                }
                // get keywords by their ids
                $this->db->select('id');
                $this->db->where_in('value', $keywords);
                $query = $this->db->get('keyword');
                // add to file_keywords table
                $keywords = array();
                foreach ($query->result() as $row) {
                    array_push($keywords, [
                        'file_id' => $id,
                        'keyword_id' => $row->id
                    ]);
                }
                $this->db->insert_batch('file_keywords', $keywords);
            }

            // Add to the search index.
            $this->db->insert('searchindex', [
                'file_id' => $id,
                'title' => $data['title'],
                'description' => $data['description'],
                'author' => $data['author'],
                'keywords' => $data['keywords']
            ]);
        }
        $this->db->trans_complete();
        return $this->db->trans_status()? $id : false;
    }

    public function update($id, $data, $comment = null)
    {
        // Check if any data changed.
        $query = $this->db->get_where('file', ['id' => $id]);
        $current = $query->row_array();
        unset($current['id']);
        $diff = array_diff($data, $current);
        if (!count($diff))
            return true;

        $this->db->trans_start();

        // Get the current revision number.
        $this->db->select('revision');
        $this->db->where('file_id', $id);
        $this->db->order_by('revision', 'desc');
        $this->db->limit(1);
        $query = $this->db->get('file_history');
        if ($query->num_rows()) {
            // We have previous revisions, get the latest revision number.
            $revision = $query->row()->revision;
        } else {
            // No revision yet exists, copy current record to history.
            $revision = 0;
            $current['file_id'] = $id;
            $this->db->insert('file_history', $current);
        }
        $current = array_replace($current, $diff);

        // Update main table with new values.
        $this->db->where('id', $id);
        $result = $this->db->update('file', $data);
        if ($result) {
            // Add to recent table.
            $this->updateRecent($id);

            // Add keywords.
            $keywords = explode("\t", $data['keywords']);
            if (count($keywords)) {
                foreach ($keywords as $value) {
                    // keyword table
                    $entry = [
                        'value' => $value,
                        'language' => $this->config->item('language')
                    ];
                    $sql = $this->db->set($entry)->get_compiled_insert('keyword');
                    $sql .= ' ON DUPLICATE KEY UPDATE id=id';
                    $this->db->simple_query($sql);

                }
                // get keywords by their ids
                $this->db->select('id');
                $this->db->where_in('value', $keywords);
                $query = $this->db->get('keyword');
                // convert result set into array for batch insert
                $keywords = array();
                foreach ($query->result() as $row) {
                    array_push($keywords, [
                        'file_id' => $id,
                        'keyword_id' => $row->id
                    ]);
                }
                // remove existing records in file_keywords
                $this->db->where('file_id', $id);
                $this->db->delete('file_keywords');
                // add to file_keywords table
                $this->db->insert_batch('file_keywords', $keywords);
            }

            // Update the search index.
            $this->db->where('file_id', $id);
            $this->db->update('searchindex', [
                'title' => $data['title'],
                'description' => $data['description'],
                'author' => $data['author'],
                'keywords' => $data['keywords']
            ]);
        }

        // Insert new values into history.
        $current['file_id'] = $id;
        $current['revision'] = $revision + 1;
        $current['updated_at'] = null;
        $current['comment'] = $comment;
        $this->db->insert('file_history', $current);

        $this->db->trans_complete();
        return $this->db->trans_status();
    }

    public function getLicenses()
    {
        return [
            'self|GFDL|cc-by-sa-all|migration=redundant' => tr('license_self|GFDL|cc-by-sa-all|migration=redundant'),
            'self|Cc-zero' => tr('license_self|Cc-zero'),
            'PD-self' => tr('license_PD-self'),
            'self|GFDL|cc-by-sa-3.0|migration=redundant' => tr('license_self|GFDL|cc-by-sa-3.0|migration=redundant'),
            'self|GFDL|cc-by-3.0|migration=redundant' => tr('license_self|GFDL|cc-by-3.0|migration=redundant'),
            'self|cc-by-sa-3.0' => tr('license_self|cc-by-sa-3.0'),
            'cc-by-sa-4.0' => tr('license_cc-by-sa-4.0'),
            'cc-by-sa-3.0' => tr('license_cc-by-sa-3.0'),
            'cc-by-4.0' => tr('license_cc-by-4.0'),
            'cc-by-3.0' => tr('license_cc-by-3.0'),
            'Cc-zero' => tr('license_Cc-zero'),
            'FAL' => tr('license_FAL'),
            'PD-old-100' => tr('license_PD-old-100'),
            'PD-old-70-1923' => tr('license_PD-old-70-1923'),
            'PD-old-70|Unclear-PD-US-old-70' => tr('license_PD-old-70|Unclear-PD-US-old-70'),
            'PD-US' => tr('license_PD-US'),
            'PD-USGov' => tr('license_PD-USGov'),
            'PD-USGov-NASA' => tr('license_PD-USGov-NASA'),
            'PD-USGov-Military-Navy' => tr('license_PD-USGov-Military-Navy'),
            'PD-ineligible' => tr('license_PD-ineligible'),
            'Copyrighted free use' => tr('license_Copyrighted free use'),
            'Attribution' => tr('license_Attribution'),
            'subst:uwl' => tr('license_subst:uwl')
        ];
    }

    public function getLicenseByKey($licenseKey)
    {
        $licenses = $this->getLicenses();
        if (array_key_exists($licenseKey, $licenses))
            return $licenses[$licenseKey];
        else
            $licenses['subst:uwl'];
    }

    private function getMostRecent()
    {
        $this->db->select('id, file_id');
        $this->db->order_by('updated_at', 'desc');
        $this->db->limit(1);
        $query = $this->db->get('recent');
        return ($query->num_rows() > 0)? $query->row() : null;
    }

    public function updateRecent($id)
    {
        $recent = $this->getMostRecent();
        if (!$recent || $id != $recent->file_id) {
            $this->db->insert('recent', ['file_id' => $id]);
        } else {
            // Just update the most recent one so timestamp update.
            $this->db->where('id', $recent->id);
            $this->db->update('recent', ['updated_at' => null]);
        }
    }

    public function getRecent()
    {
        $this->db->select('file_id, mime_type, title, name, file.updated_at');
        $this->db->from('recent');
        $this->db->join('file', 'file.id = file_id');
        $this->db->join('user', 'user_id = user.id');
        $this->db->order_by('recent.updated_at', 'desc');
        $this->db->limit($this->config->item('recent_limit'));
        $query = $this->db->get();
        return $query->result_array();
    }

    public function search($query)
    {
        if (is_array($query)) {
            $conditions = array();
            //TODO add sort order to advanced search
            $relevance = 'file.updated_at';
            if (!empty($query['title'])) {
                $conditions []= "(MATCH (searchindex.title) AGAINST ('$query[title]' IN BOOLEAN MODE))";
            }
            if (!empty($query['description'])) {
                $conditions []= "(MATCH (searchindex.description) AGAINST ('$query[description]' IN BOOLEAN MODE))";
            }
            if (!empty($query['author'])) {
                $conditions []= "(MATCH (searchindex.author) AGAINST ('$query[author]' IN BOOLEAN MODE))";
            }
            if (!empty($query['keywords'])) {
                $conditions []= "(MATCH (searchindex.keywords) AGAINST ('$query[keywords]' IN BOOLEAN MODE))";
            }
            if (!empty($query['language'])) {
                $conditions []= "(file.language = '$query[language]')";
            }
            if (!empty($query['license'])) {
                $conditions []= "(file.license = '$query[license]')";
            }
            if (!empty($query['date_from'])) {
                $conditions []= "(file.recording_date >= '$query[date_from]')";
            }
            if (!empty($query['date_to'])) {
                $conditions []= "(file.recording_date <= '$query[date_to]')";
            }
            if (count($conditions) > 0)
                $this->db->where(implode(' AND ', $conditions));
        } else {
            $query = stripslashes(str_replace('&quot;', '"', $query));
            $match = "MATCH (searchindex.title, searchindex.description, searchindex.author, searchindex.keywords) AGAINST ('$query' IN BOOLEAN MODE)";
            $relevance = $match;
            $this->db->where($match);
        }
        $this->db->select("file_id, file.mime_type, file.title, file.author, name, file.updated_at, $relevance as relevance");
        $this->db->from('searchindex');
        $this->db->join('file', 'file.id = file_id');
        $this->db->join('user', 'user_id = user.id');
        $this->db->order_by('relevance', 'desc');
        $this->db->limit($this->config->item('search_limit'));
        $query = $this->db->get();
        return $query->result_array();
    }

    public function delete($id)
    {
        $this->db->trans_start();

        // Mark latest record in file_history as deleted.
        $this->db->set('is_delete', 1);
        $this->db->set('deleted_at', null);
        $this->db->where('file_id', $id);
        $this->db->order_by('revision', 'desc');
        $this->db->limit(1);
        $this->db->update('file_history');

        // Delete records from main file and related tables.
        $this->db->delete('file', ['id' => $id]);
        $this->db->delete('recent', ['file_id' => $id]);
        $this->db->delete('missing_files', ['file_id' => $id]);
        $this->db->where('file_id', $id);
        $this->db->or_where('child_id', $id);
        $this->db->delete('file_children');
        $this->db->trans_complete();
        // searchindex is MyISAM, which does not support transactions.
        if ($this->db->trans_status())
            $this->db->delete('searchindex', ['file_id' => $id]);
    }

    public function getByUserId($id)
    {
        $this->db->select('id, mime_type, title, author, updated_at');
        $this->db->order_by('updated_at', 'desc');
        $query = $this->db->get_where('file', ['user_id' => $id]);
        return $query->result_array();
    }

    public function getHistory($id)
    {
        $this->db->select('file_history.id, revision, file_history.updated_at, name');
        $this->db->join('user', 'user_id = user.id');
        $this->db->where('file_id', $id);
        $this->db->order_by('revision', 'desc');
        return $this->db->get('file_history')->result_array();
    }

    public function getHistoryById($id)
    {
        $this->db->where('id', $id);
        $query = $this->db->get('file_history');
        return $query->row_array();
    }

    public function getHistoryByRevision($file_id, $revision)
    {
        $this->db->select('title, author, description, keywords, language, license, recording_date, updated_at, comment, properties');
        $this->db->where(['file_id' => $file_id, 'revision' => $revision]);
        $query = $this->db->get('file_history');
        return $query->row_array();
    }

    function staticUpdate($id, $data)
    {
        $this->db->where('id', $id);
        $this->db->set($data);
        $this->db->set('updated_at', 'updated_at', false);
        return $this->db->update('file');
    }

    public function getByHash($hash)
    {
        $this->db->where(['source_hash' => $hash]);
        $this->db->or_where(['output_hash' => $hash]);
        $query = $this->db->get('file', 1);
        return $query->row_array();
    }

    public function getByPath($path)
    {
        $this->db->like(['source_path' => $path]);
        $this->db->or_like(['output_path' => $path]);
        $query = $this->db->get('file', 1);
        return $query->row_array();
    }

    public function addChild($parentId, $childId)
    {
        $this->db->where('file_id', $parentId);
        $this->db->where('child_id', $childId);
        if ($this->db->get('file_children')->num_rows() > 0)
            return true;
        $result = $this->db->insert('file_children', [
            'file_id' => $parentId,
            'child_id' => $childId
        ]);
        if ($result)
            return $this->db->insert_id();
        else
            return false;
    }

    public function addMissing($fileId, $name, $hash)
    {
        $this->db->where('file_id', $fileId);
        $this->db->where('hash', $hash);
        if ($this->db->get('missing_files')->num_rows() > 0)
            return true;
        $result = $this->db->insert('missing_files', [
            'file_id' => $fileId,
            'name' => $name,
            'hash' => $hash
        ]);
        if ($result)
            return $this->db->insert_id();
        else
            return false;
    }

    public function getMissingFiles($id)
    {
        $this->db->select('name');
        $this->db->where('file_id', $id);
        return $this->db->get('missing_files')->result_array();
    }

    public function getChildren($id)
    {
        $this->db->select('child_id, file.mime_type, file.title, file.author');
        $this->db->where('file_id', $id);
        $this->db->join('file', 'child_id = file.id');
        return $this->db->get('file_children')->result_array();
    }

    public function getProjects($id)
    {
        $this->db->select('file.id, file.title, file.author, file.updated_at');
        $this->db->where('child_id', $id);
        $this->db->join('file', 'file_id = file.id');
        $this->db->order_by('file.title');
        return $this->db->get('file_children')->result_array();
    }

    public function getMissingByHash($hash)
    {
        return $this->db->get_where('missing_files', ['hash' => $hash])->result_array();
    }

    public function deleteMissing($id)
    {
        $this->db->where('id', $id)->delete('missing_files');
    }

    public function getProperties($id)
    {
        $this->db->select('properties');
        $query = $this->db->get_where('file', ['id' => $id]);
        if ($query->num_rows()) {
            return json_decode($query->row()->properties, true);
        } else {
            return array();
        }
    }
}

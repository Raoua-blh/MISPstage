<?php
App::uses('AppModel', 'Model');

class FuzzyCorrelateSsdeep extends AppModel
{
    public $useTable = 'fuzzy_correlate_ssdeep';

    public $recursive = -1;

    public function ssdeep_prepare($hash)
    {
        list($block_size, $hash) = explode(':', $hash, 2);
        
        $chars = array();
        for ($i = 0; $i < strlen($hash); $i++) {
            if (!in_array($hash[$i], $chars, true)) {
                $chars[] = $hash[$i];
            }
        }
        $search = true;
        while ($search) {
            $search = false;
            foreach ($chars as $c) {
                if (strpos($hash, $c . $c . $c . $c)) {
                    $hash = str_replace($c . $c . $c . $c, $c . $c . $c, $hash);
                    $search = true;
                }
            }
        }
        $hash = explode(':', $hash);
        $block_data = $hash[0];
        $double_block_data = $hash[1];
        //        (struct.unpack("<Q", base64.b64decode(h[i:i + 7] + "=") + "\x00\x00\x00")[0] for i in range(len(h) - 6)))

        $result = array(
            $block_size,
            $this->get_all_7_char_chunks($block_data),
            $this->get_all_7_char_chunks($double_block_data)
        );
        return $result;
    }

    public function get_all_7_char_chunks($hash)
    {
        $results = array();
        for ($i = 0; $i < strlen($hash) - 6; $i++) {
            $current = substr($hash, $i, 7);
            $temp = $current . '=';
            $temp = base64_decode($temp);
            $temp = $temp . "\x00\x00\x00";
            $temp = base64_encode($temp);
            if (!in_array($temp, $results, true)) {
                $results[] = $temp;
            }
        }
        return $results;
    }

    public function query_ssdeep_chunks($hash, $attributeId)
    {
        $chunks = $this->ssdeep_prepare($hash);
        
        // Original algo from article https://www.virusbulletin.com/virusbulletin/2015/11/optimizing-ssdeep-use-scale
        // also propose to insert chunk size to database, but current database schema doesn't contain that column.
        // This optimisation can be add in future versions.
        $result = $this->find('column', array(
            'conditions' => array(
                'FuzzyCorrelateSsdeep.chunk' => array_merge($chunks[1], $chunks[2]),
            ),
            'fields' => array('FuzzyCorrelateSsdeep.attribute_id'),
            'unique' => true,
        ));
        
        $toSave = [];
        $attributeId = (int) $attributeId;
        foreach (array(1, 2) as $type) {
            foreach ($chunks[$type] as $chunk) {
                $toSave[] = [$attributeId, $chunk];
            }
        }
        if (!empty($toSave)) {
            $db = $this->getDataSource();
            $db->insertMulti($this->table, ['attribute_id', 'chunk'], $toSave);
        }
        return $result;
    }

    /**
     * @param int|null $eventId
     * @param int|null $attributeId
     * @return bool True on success, false on failure
     */
    public function purge($eventId = null, $attributeId = null)
    {
        if (!$eventId && !$attributeId) {
            $this->query('TRUNCATE TABLE fuzzy_correlate_ssdeep;');
        } elseif (!$attributeId) {
            $this->Attribute = ClassRegistry::init('Attribute');
            $attributeId = $this->Attribute->find('column', array(
                'conditions' => array(
                    'Attribute.event_id' => $eventId,
                    'Attribute.type' => 'ssdeep',
                ),
                'fields' => ['Attribute.id'],
            ));
            if (empty($attributeId)) {
                return true;
            }
        }

        return $this->deleteAll(array('FuzzyCorrelateSsdeep.attribute_id' => $attributeId), false);
    }
}

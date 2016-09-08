<?php

// Start with an empty array of item metadata
$multipleItemMetadata = array();

// Loop through each item, picking up the minimum information needed.
// There will be no pagination, since the amount of information for each
// item will remain quite small.

/*
//ORIGINAL:
foreach( loop( 'item' ) as $item )
{
	// If it doesn't have location data, we're not interested.
	$hasLocation = get_db()->getTable( 'Location' )->findLocationByItem( $item, true );
	if( $hasLocation )
	{
		$itemMetadata = $this->itemJsonifier( $item , false);
		array_push( $multipleItemMetadata, $itemMetadata );
	}
}
*/

$itemIdList = "";

//$itemArray = array();

//Get list of item ids
foreach( loop( 'item' ) as $item )
{
	if ($itemIdList) {
	   $itemIdList .= ",";
	}
	$itemIdList .= $item->id;

	//Save item - need later to get thumbnail
//	$itemArray[$item->id] = $item;
}


$db = get_db();

$sql = "
SELECT oi.id, 
       oi.featured, 
       ol.latitude, 
       ol.longitude,
       oet1.text 'title',
       oet2.text 'address',
       of.filename
FROM `omeka_items` oi
JOIN omeka_locations ol 
  ON oi.id = ol.item_id
JOIN (omeka_element_texts oet1, omeka_elements oe1) 
  ON (oi.id = oet1.record_id 
      AND oet1.record_type = 'Item' 
      AND oet1.element_id=oe1.id 
      AND oe1.name='Title')
LEFT JOIN (omeka_element_texts oet2, omeka_elements oe2) 
  ON (oi.id = oet2.record_id 
      AND oet2.record_type = 'Item' 
      AND oet2.element_id=oe2.id 
      AND oe2.name='Street Address')
LEFT JOIN (omeka_files of) 
  ON (oi.id = of.item_id
      AND of.mime_type IN ('application/pdf', 'image/jpeg', 'image/jpg', 'image/png', 'image/gif')
      AND (of.order IS NULL OR of.order = 1))
WHERE oi.id IN ($itemIdList)
ORDER BY oi.id, of.id
";


$result_array = $db->fetchAll($sql);

//need to skip extra records when item has more than one image returned,
//since multiple files for an item may have order = NULL
$prev_item_id = 0;

if ($result_array) {
   foreach( $result_array as $record )
   {
	if ($record['id'] == $prev_item_id) {
	   continue;
	}
	
	//Fix title
	$record['title'] = html_entity_decode(strip_formatting($record['title']));

	//Fix address - changed to empty string if null
	//**TODO** can probably do this in SQL
	if ($record['address'] === NULL) {
	    $record['address'] = "";
	}

	//Check for thumbnail

/*
	$thumbUrl = item_image('square_thumbnail', array(), 0, $itemArray[$record['id']]);


	if ($thumbUrl) {
	     $record['thumbnail'] = (preg_match('/<img(.*)src(.*)=(.*)"(.*)"/U', $thumbUrl, $pregResult))
	     ? array_pop($pregResult)
	     : null;
				
	   //$record['thumbnail'] = $thumbUrl;
	}
*/

	//**TODO** Not checking if thumbnail exists before passing back URL
	if (! is_null($record['filename'])) {
	     //replace any other extension with .jpg
	     $filename = preg_replace('/\\.[a-z]{3,4}/', '', $record['filename']) . ".jpg";
	     $record['thumbnail'] = WEB_ROOT . "/files/square_thumbnails/$filename";
	     //$record['thumbnail'] = $filename;

	}

	//remove 'filename' from array
	unset($record['filename']);

	array_push($multipleItemMetadata, $record);

	$prev_item_id = $record['id'];
   }
}


$metadata = array(
	'items'        => $multipleItemMetadata,
	'total_items'  => count( $multipleItemMetadata )
);

echo Zend_Json_Encoder::encode( $metadata );

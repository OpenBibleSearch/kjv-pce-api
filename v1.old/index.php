<?php
    include('../api_settings.php');
    include('../functions.php');
    include('../get_vars.php');
    
    if(array_search($key, $_api_keys)) {
        $db = new SQLite3($_db);

        if($get_section) {
            if(($get_section == 'OT') || ($get_section == 'NT')) {
                if($include_data) {
                    $columns = '*';
                } else {
                    $columns = 'BookID,BookName,BookAbr';
                }
                $idRange = makeSectionQuery($get_section);
                $query = "SELECT " . $columns . " FROM Bible WHERE BookID " . $idRange . " GROUP BY BookID ORDER BY BookID";
            }
        } elseif($get_books) {
            $query = "SELECT BookID,BookName,BookAbr FROM Bible";
            if($section) {
                $idRange = makeSectionQuery($section);
                $query .= " WHERE BookID " . $idRange;
            }
            $query .= " GROUP BY BookID ORDER BY BookID";
        } elseif($get_chapters) {
            if($book) {

                if($include_data) {
                    $columns = '*';
                } else {
                    $columns = 'Chapter';
                }

                $query = "SELECT " . $columns . " FROM Bible WHERE ";
                if(is_numeric($book)) {
                    $book_query = "BookID='" . $book . "'";
                } else {
                    if(strlen($book) > 3) {
                        $book_query = "BookName='" . $book . "'";
                    } else {
                        $book_query = "BookAbr='" . $book . "'";
                    }
                }
                $query .= $book_query . " GROUP BY BookID,Chapter ORDER BY Chapter";
            }
        } elseif($get_verses) {
            if(($book) && ($chapter)) {

                if($include_data) {
                    $columns = '*';
                } else {
                    $columns = 'Verse';
                }

                $query = "SELECT " . $columns . " FROM Bible WHERE ";
                if(is_numeric($book)) {
                    $book_query = "BookID='" . $book . "'";
                } else {
                    if(strlen($book) > 3) {
                        $book_query = "BookName='" . $book . "'";
                    } else {
                        $book_query = "BookAbr='" . $book . "'";
                    }
                }
                $query .= $book_query;
                $query .= " AND Chapter='" . $chapter . "'";
                $query .= " GROUP BY Chapter, Verse ORDER BY Chapter";
            }
        } else {
            $query = "SELECT * FROM Bible WHERE ";
            $and = false;

            if($section) {
                $idRange = makeSectionQuery($section);
                if($and) {
                    $query .= " AND ";
                }
                $query .= "BookID " . $idRange;
                $and = true;
            }

            if($book) {
                if(is_numeric($book)) {
                    $book_query = "BookID='" . $book . "'";
                } else {
                    if(strlen($book) > 3) {
                        $book_query = "BookName='" . $book . "'";
                    } else {
                        $book_query = "BookAbr='" . $book . "'";
                    }
                }
                if($and) {
                    $query .= " AND ";
                }
                $query .= $book_query;
                $and = true;
            }

            if(($chapter) && (is_numeric($chapter))) {
                if($and) {
                    $query .= " AND ";
                }
                $query .= "Chapter='" . $chapter . "'";
                $and = true;
            }

            if($verses) {

                $explode_dash = explode('-', $verses);
                $explode_comma = explode(',', $verses);
                $range = array();
                $verse_query = '';

                if((strpos($verses, '-') && (count($explode_dash) > 0))) {
                    $start = $explode_dash[0];
                    $end = $explode_dash[1];
                    if((is_numeric($start)) && (is_numeric($end))) {
                        $range = range($start,$end);
                    }
                } elseif((strpos($verses, ',')) && (count($explode_comma))) {
                    if(count($explode_comma) > 0) {
                        foreach($explode_comma as $item) {
                            if(is_numeric($item)) {
                                array_push($range, $item);
                            }
                        }
                    }
                } else {
                    if(is_numeric($verses)) {
                        array_push($range, $verses);
                    }
                }

                if(count($range) == 1) {
                    $verse_query = "Verse='" . $range[0] . "'";
                } elseif(count($range) > 1) {
                    $verse_query = "Verse IN(";
                    $counter = 0;
                    foreach($range as $x) {
                        if($counter > 0) {
                            $verse_query .= ",";
                        }
                        $verse_query .= "'" . $x . "'";
                        $counter++;
                    }
                    $verse_query .= ')';
                } else {
                    $verse_query = '';
                }

                if($and) {
                    $query .= " AND ";
                }
                $query .= $verse_query;
                if($verse_query) {
                    $and = true;
                }
            }

            if(($keyword) && ($match)) {
                $keyword_query = "VText LIKE ";

                if($match == 'contains') {
                    $keyword_query .= "'%" . $keyword . "%'";
                } elseif($match == 'startswith') {
                    $keyword_query .= "'" . $keyword . "%'";
                } elseif($match == 'endswith') {
                    $keyword_query .= "'%" . $keyword . "'";
                } elseif($match == 'exact') {
                    $keyword_query .= "'% " . $keyword . " %'";
                } else {
                    $keyword_query = '';
                }

                if($and) {
                    $query .= " AND ";
                }
                $query .= $keyword_query;

                if($keyword_query) {
                    $and = true;
                }
            }
        }

        if($case_insensitive) {
            $query .= " COLLATE NOCASE";
        }

        try {
            $data = array();
            $data['query'] = $query;
            $data['query_success'] = false;
            $data['result_count'] = 0;
            $data['results'] = array();

            $result = $db->query($query) or die(print(json_encode($data)));
            if(count($result) > 0) {
                $data['query_success'] = true;
                $new_data = array();
                while ($row = $result->fetchArray(SQLITE3_ASSOC)) {

                    if($row['BookID']) {
                        $row['BookID'] = intval($row['BookID']);
                    }

                    if($row['Chapter']) {
                        $row['Chapter'] = intval($row['Chapter']);
                    }

                    if($row['Verse']) {
                        $row['Verse'] = intval($row['Verse']);
                    }

                    if($row['BookID'] <= 39) {
                        $row['Section'] = 'OT';
                    } else {
                        $row['Section'] = 'NT';
                    }

                    array_push($new_data,$row);
                }

                $data['result_count'] = count($new_data);
                $data['results'] = $new_data;
            }

            $json = json_encode($data);
            print($json);
        } catch(Exception $exception) {
            echo $exception->getMessage();
        }
    } else {
        print('You must get an API key assigned.  They are free, just email <a href="mailto:psyclone241@gmail.com">'
            . 'psyclone241@gmail.com</a> and request an API Key');
    }

    function makeSectionQuery($section) {
        if($section == 'OT') {
            $ids = range(1,39);
        } else {
            $ids = range(40,66);
        }

        $idCount = 0;
        $idRange = "IN(";
        foreach($ids as $id) {
            if($idCount > 0) {
                $idRange .= ',';
            }
            $idRange .= "'" . $id . "'";
            $idCount++;
        }
        $idRange .= ")";
        return $idRange;
    }
?>
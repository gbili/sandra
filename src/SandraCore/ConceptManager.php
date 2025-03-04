<?php

namespace SandraCore;

use PDO;
use phpDocumentor\Reflection\Types\Boolean;

class ConceptManager
{

    // This is the CLASS DEFINITION (everything in the curly brackets). test

    public $concepts;
    protected $filter, $filterSQL, $tableLink, $tableRef, $tableConcept;
    private $system;
    private $pdo;
    private $deletedUnid;
    public $conceptArray = array();
    public $mainQuerySQL = null ;
    private $bypassFlags = false;
    private $lastLinkJoined = null;

    public function __construct(System $system, $su = 1, $tableLinkParam = 'default', $tableReferenceParam = 'default')
    {

        $this->filterSQL = '';
        $this->filterJoin = '';
        $this->filterCondition = '';
        $this->concepts = array();

        $this->su = $su;
        $this->system = $system;


        $this->deletedUnid = $system->deletedUNID;


        $this->pdo = System::$pdo->get();

        //table as instance data
        if ($tableLinkParam == 'default') {
            $this->tableLink = $system->linkTable;

        } else {
            $this->tableLink = $tableLinkParam;

        }

        if ($tableReferenceParam == 'default') {
            $this->tableReference = $system->tableReference;
        } else {

            $this->tableReference = $tableReferenceParam;
        }

        //$this->setFilter(array()); //
        $this->setFilter(array()); //

    }

    public function bypassFlags(bool $boolean)
    {
        $this->bypassFlags = $boolean;

    }

    public function setFilter($value, $limit = 0)
    {
        if (!is_array($value)) {
            $value = array();
        }

        $this->filter = $value;
        $this->buildFilterSQL();
    }

    public function buildFilterSQL($limit = 0)
    {
        global $tableLink, $tableReference, $existenseStatusUNID, $deletedUNID;

        $deletedUNID = $this->system->deletedUNID;


        //build the filter
        $join = '';
        $conditionnalClause = '';
        $tableCounter = 0;

        foreach ($this->filter as $link => $targetConcept) {
            //echoln("$link = key value $targetConcept[lklk]");

            $tableCounter++;

            $this->lastLinkJoined = 'link' . $tableCounter;

            if (!$this->bypassFlags)
                $flag = "AND link$tableCounter.flag != $deletedUNID";
            else $flag = '';


            if ($targetConcept['lktg'] == 0 && $targetConcept['lklk'] == 0)
                continue;

            $mainConcept = 'idConceptStart';
            $secondaryConcept = 'idConceptTarget';

            if (isset($targetConcept['reverse']) && ($targetConcept['reverse'] == 1)) {
                $mainConcept = 'idConceptTarget';
                $secondaryConcept = 'idConceptStart';
            }

            if (empty($targetConcept['exclusion'])) {

                if (isset($targetConcept['logic']) && ($targetConcept['logic'] == 'OR'))
                    $logic = 'OR';
                else
                    $logic = 'AND';


                //it's an inclusion filter
                if ($targetConcept['lktg'] == 0) {


                    $join .= "JOIN  $this->tableLink link$tableCounter ON link$tableCounter.$mainConcept = l.idConceptStart ";
                    $conditionnalClause .= "  $flag AND link$tableCounter.idConceptLink IN($targetConcept[lklk])";
                } //any filter if the link equal 0 then make the filter on ANY link
                else if ($targetConcept['lklk'] == 0) {


                    $join .= " JOIN  $this->tableLink link$tableCounter ON link$tableCounter.$mainConcept = l.idConceptStart ";
                    $conditionnalClause .= " $flag AND link$tableCounter.$secondaryConcept IN($targetConcept[lktg])";
                } else {


                    $join .= " JOIN  $this->tableLink link$tableCounter ON link$tableCounter.$mainConcept = l.idConceptStart ";
                    $conditionnalClause .= " $flag AND 
			link$tableCounter.$secondaryConcept IN($targetConcept[lktg]) AND link$tableCounter.idConceptLink IN($targetConcept[lklk])";
                }
            } else {
                //eclusion filter
                if ($targetConcept['lktg'] == 0) {

                    $join .= " LEFT JOIN  $this->tableLink link$tableCounter ON link$tableCounter.idConceptStart = l.idConceptStart 
			 						  $flag
			 						  AND link$tableCounter.idConceptLink IN($targetConcept[lklk])";
                    $conditionnalClause .= " 
									  AND link$tableCounter.idConceptLink IS NULL";
                } //any filter if the link equal 0 then make the filter on ANY link
                else if ($targetConcept['lklk'] == 0) {


                    $join .= " LEFT JOIN  $this->tableLink link$tableCounter ON link$tableCounter.idConceptStart = l.idConceptStart 
					  $flag
					  AND link$tableCounter.idConceptTarget IN($targetConcept[lktg]) ";
                    $conditionnalClause .= " 
									 AND link$tableCounter.idConceptTarget IS NULL";
                } else {


                    $join .= " LEFT JOIN  $this->tableLink link$tableCounter ON link$tableCounter.idConceptStart = l.idConceptStart 
	 $flag 
			AND link$tableCounter.idConceptTarget IN ($targetConcept[lktg]) 
			AND link$tableCounter.idConceptLink  IN( $targetConcept[lklk])";
                    $conditionnalClause .= "
			AND link$tableCounter.idConceptTarget IS NULL";
                }
            }
        }

        $this->filterJoin = $join;
        $this->filterCondition = $conditionnalClause;
    }

    public function getConcepts()
    {


        return $this->concepts;
    }

    //Check the followup if something needs to be done



    public function getResultsFromLink($linkId)
    {
        global $tableLink, $dbLink;

        $sql = "SELECT  l.idConceptStart, l.idConceptLink, l.`idConceptTarget` FROM  $this->tableLink l WHERE l.id = $linkId";
        $resultat = mysqli_query($dbLink, $sql);

        while ($result = mysqli_fetch_array($resultat)) {
            $idConceptStart = $result['idConceptStart'];
            $idConceptLink = $result['idConceptLink'];
            $idConceptTarget = $result['idConceptTarget'];
            $resultsArray[] = $idConceptStart;
            array_push($resultsArray, $idConceptLink);
            array_push($resultsArray, $idConceptTarget);
            $this->conceptArray['conceptStartList'][] = $idConceptStart;
        }

        if (isset($resultsArray))
            return $resultsArray;
    }

    //Check the followup if something needs to be done
    public function getConceptsFromLinkAndTarget($linkConcept, $targetConcept, $limit = 0, $asc = 'ASC', $offset = 0, $countOnly = false, $orderByRefConcept = null, $numberSort = false)
    {
        global $tableLink, $tableReference, $deletedUNID, $includeCid, $containsInFileCid, $dbLink;

        $hideLinks = "";

        if ($limit > 0)
            $limitSQL = "LIMIT $limit ";
        else
            $limitSQL = '';

        if (is_numeric($offset)){
            $offsetSQL = "OFFSET $offset ";

        }
        else{
            $offsetSQL = "";
        }

        //sub optimal to remove soon

        if (!isset($_SESSION['accessToFiles'])) {

            $_SESSION['accessToFiles'] = 0;
        }

        if ($this->su == 0 && isset($_SESSION['accessToFiles'])) {
            $comma_separated = implode(",", $_SESSION['accessToFiles']);

            $hideLinks = "AND 
            (
            l.idConceptStart IN(SELECT idConceptStart FROM  $this->tableLink WHERE idConceptTarget IN($comma_separated) AND idConceptLink IN ($includeCid, $containsInFileCid ) ) 
            OR l.idConceptStart NOT IN ( SELECT idConceptStart FROM `$this->tableLink` WHERE idConceptLink IN ($includeCid, $containsInFileCid) )
            )";
        }


        $lastLinkJoined = $this->lastLinkJoined;

        if (!$this->lastLinkJoined) {
            $lastLinkJoined = 'l';
        }

        //Due to a supposed MySQL optimizer bug we order by the last joined table
        $orderBy = "ORDER BY $lastLinkJoined.idConceptStart";


        $joinSorter = '';
        $sorterWhere = '';
        //we sort by refConcepts
        if ($orderByRefConcept) {
            $sortableRef = CommonFunctions::somethingToConceptId($orderByRefConcept, $this->system);

            $joinSorter = "JOIN $this->tableReference refSorter ON l.id = refSorter.linkReferenced ";
            $sorterWhere = " AND refSorter.idConcept = $sortableRef  ";
            if (!$numberSort) {
                $orderBy = " ORDER BY refSorter.value";
            } else {
                $orderBy = " ORDER BY CAST(refSorter.value AS DECIMAL)";
            }
        }


        $flag = '';

        if (!$this->bypassFlags)
            $flag = "AND l.flag != $deletedUNID";

        //build selector
        $selector = "SELECT  l.idConceptStart, l.idConceptLink, l.`idConceptTarget` ";

        if ($countOnly)
            $selector = "SELECT  COUNT(l.idConceptStart) AS result";



        $sql = "$selector FROM  $this->tableLink l " .
            $this->filterJoin . $joinSorter . "
	WHERE l.idConceptLink = $linkConcept  
	AND l.idConceptTarget = $targetConcept
	$flag $sorterWhere
	" . $this->filterCondition . " $hideLinks $orderBy $asc " . $limitSQL .$offsetSQL;


        // echoln( "su = $this->su access to file". $_SESSION['accessToFiles']);
        
        $this->mainQuerySQL = $sql ;


        try {
            $pdoResult = $this->pdo->prepare($sql);
            $pdoResult->execute();

        } catch (PDOException $exception) {

            System::sandraException($exception);
            return;
        }


        foreach ($pdoResult->fetchAll(PDO::FETCH_ASSOC) as $result) {

            if ($countOnly)
                return $result['result'] ;

            $idConceptStart = $result['idConceptStart'];
            $array[] = $idConceptStart;
            $this->concepts[] = new Concept($idConceptStart, $this->system);
            $this->conceptArray['conceptStartList'][] = $idConceptStart;
        }

        if (isset($array))
            return $array;
    }



    public function getConceptsFromLink($linkConcept, $limit = 0, $debug = '')
    {
        global $tableLink, $tableReference, $deletedUNID, $dbLink;
        //look at the followup object

        $hideLinks = "";

        if ($limit > 0)
            $limitSQL = "LIMIT $limit";
        else
            $limitSQL = '';

        //sub optimal to remove soon 
        if ($this->su == 0 && isset($_SESSION['accessToFiles'])) {
            $comma_separated = implode(",", $_SESSION['accessToFiles']);
            $hideLinks = "AND 
            (
            l.idConceptStart IN(SELECT idConceptStart FROM  $this->tableLink WHERE idConceptTarget IN($comma_separated) AND idConceptLink IN ($includeCid, $containsInFileCid ) ) 
            OR l.idConceptStart NOT IN ( SELECT idConceptStart FROM ` $this->tableLink` WHERE idConceptLink IN ($includeCid, $containsInFileCid) )
            )";
        }


        $flag = '';

        if (!$this->bypassFlags)
            $flag = "AND l.flag != $deletedUNID";


        $sql = "SELECT  l.idConceptStart, l.idConceptLink, l.`idConceptTarget` FROM  $this->tableLink l " .
            $this->filterJoin . "
	
	AND l.idConceptLink = $linkConcept
	$flag 
	" . $this->filterCondition . " $hideLinks ORDER BY l.idConceptStart DESC " . $limitSQL;

        if ($debug)
            echoln($sql);

        //echo"$sql";
        $resultat = mysqli_query($dbLink, $sql); //action;;

        while ($result = mysqli_fetch_array($resultat)) {
            $idConceptStart = $result['idConceptStart'];
            $idConceptTarget = $result['idConceptTarget'];

            //be s
            // $array[] = $idConceptTarget;
            $array[] = $idConceptStart;

            $this->concepts[] = new Concept($idConceptStart);
            $this->conceptArray['conceptStartList'][] = $idConceptStart;
            $this->conceptArray['conceptTargetList'][] = $idConceptTarget;
        }

        if (isset($array))
            return $array;
    }


    public function getReferences($idConceptLink = 0, $idConceptTarget = 0, $refIdArray = null, $isTargetList = 0, $byTripletid = 0, $orderByRefConcept = null, $numberSort = false)
    {
        global $deletedUNID;

        /*Note about $byTripletid. The goal is to patch the system if there are different reference on the same idConcept link the first version overight the changes. By adding the variable $byTripletid whe change the form of the result array

          [397323] => Array
        (
            [1695721] => Array here the link Id as key
                (
                    [394452] => 3
                    [linkId] => 1695721
                )

            [1695726] => Array
                (
                    [394452] => 8
                    [linkId] => 1695726
                )
        */

        $masterCondition = 'idConceptStart';
        //Choose a list of concept start or concept target
        if ($isTargetList == 0) {
            $list = 'conceptStartList';
        } else {
            $list = 'conceptTargetList';
            $masterCondition = 'idConceptTarget';
        }


        $array = null;
        //si conceptarray est un array, et qu'ilcontien un index dont le nom est la valeur de $list
        //et que cet index est un array initialisé et qui contien plus de 0 elements
        //et que refidarray est nul ou est un array
        if (
            is_array($this->conceptArray)
            && isset($this->conceptArray[$list])
            && is_array($this->conceptArray[$list])
            && (sizeof($this->conceptArray[$list]) > 0)
            && (is_null($refIdArray) || is_array($refIdArray))
        ) {
            $concepts = implode(",", $this->conceptArray[$list]);

            $refsFilter = '';
            if (!empty($refIdArray)) {
                $refs = implode(",", $refIdArray);
                $refsFilter = "WHERE r.idConcept IN ($refs)";
                //$sql .= " AND `$this->tableReference `.idConcept IN ($refs)";
            }

            $joinSorter = '';
            $sorterWhere = '';
            $orderBy = '';
            //we sort by refConcepts
            if ($orderByRefConcept) {
                $sortableRef = CommonFunctions::somethingToConceptId($orderByRefConcept, $this->system);

                $joinSorter = "JOIN $this->tableReference refSorter ON x.id = refSorter.linkReferenced";
                $sorterWhere = " AND  refSorter.idConcept = $sortableRef ";
                if (!$numberSort) {
                    $orderBy = " ORDER BY refSorter.value";
                } else {
                    $orderBy = " ORDER BY CAST(refSorter.value AS DECIMAL)";
                }

            }


            $filter = '';
            if ($idConceptLink > 0) {
                $filter .= " AND y.idConceptLink = $idConceptLink ";
            }
            if ($idConceptTarget > 0) {
                $filter .= " AND y.idConceptTarget = $idConceptTarget ";
            }

            $flag = '';
            if (!$this->bypassFlags)
                $flag = "AND x.flag != $deletedUNID";

            $sql = "
			SELECT r.id, r.idConcept, r.linkReferenced, r.value, x.idConceptStart, x.idConceptLink, x.idConceptTarget, x.id
  FROM `$this->tableReference` r
  JOIN  $this->tableLink x
    ON x.id = r.linkreferenced
  JOIN  $this->tableLink y
    ON y.id = r.linkReferenced 
  $joinSorter $refsFilter $sorterWhere 
   AND y.$masterCondition IN ($concepts) 
   $filter " . $flag . $orderBy;
            //AND flag != $deletedUNID";


            try {
                $pdoResult = $this->pdo->prepare($sql);
                $pdoResult->execute();
            } catch (PDOException $exception) {

                System::sandraException($exception);
                return;
            }

            $array = array();

            $resultArray = $pdoResult->fetchAll(PDO::FETCH_ASSOC);


            if ($byTripletid) {
                foreach ($resultArray as $key => $result) {
                    $idConcept = $result[$masterCondition];
                    $value = $result['value'];
                    $array[$idConcept][$result['id']][$result['idConcept']] = $value;
                    $array[$idConcept][$result['id']]['linkId'] = $result['id'];
                    $array[$idConcept][$result['id']]['idConceptTarget'] = $result['idConceptTarget'];
                    $array[$idConcept][$result['id']]['idConceptLink'] = $result['idConceptLink'];

                }

            } else {

                foreach ($resultArray as $key => $result) {

                    $value = $result['value'];
                    $idConcept = $result[$masterCondition];
                    $array[$idConcept][$result['idConcept']] = $value;
                    $array[$idConcept]['linkId'] = $result['id'];
                    $array[$idConcept]['idConceptTarget'] = $result['idConceptTarget'];
                }

            }
        }



        return $array;
    }
        public function getTriplets($lklkArray = null, $lktgArray = null, $getIds = 0)
    {
        global $tableReference, $tableLink, $deletedUNID, $dbLink;

        if (!key_exists('conceptStartList',$this->conceptArray)) return array();

        $array = null;
        if (is_array($this->conceptArray['conceptStartList']) && (sizeof($this->conceptArray['conceptStartList']) > 0)) {
            $concepts = implode(",", $this->conceptArray['conceptStartList']);

            $sql = "
			SELECT * FROM  $this->tableLink WHERE idConceptStart IN ($concepts)
			AND flag != $deletedUNID";
            if ((!empty($lklkArray)) && is_array($lklkArray)) {
                $lklks = implode(",", $lklkArray);
                $sql .= " AND idConceptLink IN ($lklks)";
            }
            if ((!empty($lktgArray)) && is_array($lktgArray)) {
                $lktgs = implode(",", $lktgArray);
                $sql .= " AND idConceptTarget IN ($lktgs)";
            }
            // AND `$this->tableReference`.linkReferenced =  $this->tableLink.id
            // AND `$this->tableReference`.idConcept IN ($refs)

            try {
                $pdoResult = $this->pdo->prepare($sql);
                $pdoResult->execute();
            } catch (PDOException $exception) {

                System::sandraException($exception);
                return null;
            }

            $array = array();
            $resultArray = $pdoResult->fetchAll(PDO::FETCH_ASSOC);

            foreach ($resultArray as $key => $result) {
                $idConcept = $result['idConceptStart'];
                if ($getIds) {
                    $idLink = $result['id'];
                    $array[$idConcept][$result['idConceptLink']][$idLink] = $result['idConceptTarget'];
                } else {
                    $array[$idConcept][$result['idConceptLink']][] = $result['idConceptTarget'];
                }
            }
        }
        return $array;
    }


    public function getConceptsFromArray($conceptArray)
    {

        foreach ($conceptArray as $value) {
            $idConceptStart = $value;
            $array[] = $idConceptStart;
            $this->concepts[] = $this->system->conceptFactory->getConceptFromShortnameOrId($idConceptStart);
            $this->conceptArray['conceptStartList'][] = $idConceptStart;
        }

        if (isset($array))
            return $array;
    }

    public function createView($conceptArray)
    {

        $sql = "SELECT  l.idConceptStart, l.idConceptLink, l.`idConceptTarget` FROM  $this->tableLink l " .
            $this->filterJoin . "
	WHERE l.idConceptLink = $linkConcept  
	AND l.idConceptTarget = $targetConcept
	AND l.flag != $deletedUNID 
	" . $this->filterCondition . " $hideLink";


    }

    public function destroy(){

        unset ($this->system) ;
        unset ($this->pdo) ;

    }




}
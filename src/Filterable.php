<?php

namespace CamiloManrique\ResourceFilter;

use Illuminate\Http\Request;

trait Filterable{

    private static function getRequestFields(Request $request){

        return !is_null($request->input("fields")) ? explode(",", $request->input('fields')) : null;
    }

    private static function getRelationships(Request $request){
        return !is_null($request->input("relationships")) ? explode(",", $request->input('relationships')) : array();
    }

    private static function getFilters(Request $request){
        $params = collect($request->all());
        return $params->filter(function ($value, $key){
            return !in_array($key, ["fields", "page_size"]);
        });
    }

    private static function processFilter($query, $column_unparsed, $value, $relationship=null){
        $parsed = preg_split("/".config("filter.column_query_modificator")."/", $column_unparsed);
        if(count($parsed) == 2){
            switch($parsed[1]){
                case "start":
                    $args = [$parsed[0], ">=", $value];
                    break;
                case "end":
                    $args = [$parsed[0], "<=", $value];
                    break;
                case "like":
                    $args = [$parsed[0], "LIKE", "%$value%"];
                    break;
                case "not":
                    $args = [$parsed[0], "!=", "%$value%"];
                    break;
                default:
                    $args = [$parsed[0], $value];
                    break;
            }
        }
        else{
            $args = [$parsed[0], $value];
        }
        if(!is_null($relationship)){
            return $query->whereHas($relationship, function($q) use($args){
                $q->where(...$args);
            });
        }
        else{
            return $query->where(...$args);

        }
    }

    private static function addFilters($query, $filters){
        $relationships = [];
        foreach($filters->all() as $key => $value){

            $filter_attr = preg_split("/".config("filter.relationship_separator")."/", $key);

            if(count($filter_attr) == 2){
                $relationship = $filter_attr[0];
                $column_unparsed = $filter_attr[1];
                if(!in_array($relationship, $relationships)){
                    array_push($relationships, $relationship);
                }
                $query = self::processFilter($query, $column_unparsed, $value, $relationship);
            }
            else{
                $column_unparsed = $filter_attr[0];
                $query = self::processFilter($query, $column_unparsed, $value);
            }

        }
        if(count($relationships) > 0){
            $query = $query->with($relationships);
        }

        return $query;

    }

    private static function addRelationships($query, $relationships){
        foreach($relationships as $relationship){
            $query = $query->with($relationship);
        }
        return $query;
    }

//    private static function addFields($query, $fields){
//
//        $relationships = array();
//        foreach($fields as $field){
//            $parse_field = preg_split("/@/", $field);
//
//            if(count($parse_field) == 2){
//                $relationship = $parse_field[0];
//                $column = $parse_field[1];
//                if(array_key_exists($relationship, $relationships)){
//                    array_push($relationships[$relationship], $column);
//                }
//                else{
//                    $relationships[$relationship] = array($column);
//                }
//            }
//            else{
//                $column = $parse_field[0];
//            }
//        }
//
//        var_dump($relationships);
//
//        foreach($relationships as $key => $value){
//            $query = $query->with($key.":".implode(",", $value));
//        }
//
//        $query = $query->with("account_info:username");
//
//        return $query;
//    }

    public static function filter(Request $request){

        //$fields = self::getRequestFields($request);
        $filters = self::getFilters($request);
        $relationships = self::getRelationships($request);

        $query = (new static)::query();
        $query = self::addRelationships($query, $relationships);
        $query = self::addFilters($query, $filters);

        return $query;



    }

    public static function filterAndGet(Request $request){

        $query_string = $request->getQueryString();

        $query = self::filter($request);
        if($request->has("page_size")){
            $query = $query->paginate($request->page_size);
            $query->withPath(($request->url()."?$query_string"));
        }
        else{
            if(config("filter.paginate_by_default")){
                $query = $query->paginate(config("filter.page_size"));
                $query->withPath(($request->url()."?$query_string"));
            }
            else{
                $query = $query->get();
            }
        }

        return $query;

    }



}
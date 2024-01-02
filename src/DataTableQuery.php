<?php 
namespace Hermawan\DataTables;


class DataTableQuery
{

    private $builder;

    private $columnDefs;

    private $filter;

    private $postQuery;

    private $countResult;
    
    private $doQueryFilter = FALSE;


    public function __construct($builder)
    {
        $this->builder = $builder;
    }

    public function setColumnDefs($columnDefs)
    {
        $this->columnDefs = $columnDefs;
        return $this;
    }

    public function postQuery($postQuery)
    {
        $this->postQuery = $postQuery;
    }

    public function filter($filter)
    {
        $this->filter = $filter;
    }


    /* End Modified column */


   /* Generating result */
    

    public function countAll()
    {
    	$builder = clone $this->builder;

        $this->countResult = $this->countResult !== NULL ? $this->countResult : $builder->countAllResults();  
        return $this->countResult;	
    }

    public function countFiltered()
    {
        $builder = clone $this->builder;
        
        $this->queryFilterSearch($builder);
         
        $this->countResult = ($this->countResult !== NULL && ! $this->doQueryFilter) ? $this->countResult : $builder->countAllResults();  

        return $this->countResult;    
    }

    public function getDataResult()
    {
        $queryResult = $this->queryResult();
        $result      = [];

        foreach ($queryResult as $row) 
        {
            //escaping all
            foreach($row as $key => $val)
                $row->$key = esc($val);

            $data    = [];
            $columns = $this->columnDefs->getColumns();

            foreach ($columns as $column) 
            {
                switch ($column->type) {
                    case 'numbering':
                        $value = $this->columnDefs->getNumbering();
                        break;
                    
                    case 'add':
                        $callback = $column->callback;
                        $value    = $callback($row);
                        break;
                    
                    case 'edit':
                        $callback = $column->callback;
                        $value    = $callback($row);
                        break;
                    
                    case 'format':
                        $callback = $column->callback;
                        $value    = $callback($row->{$column->alias});
                        break;
                    
                    default:
                        $value = $row->{$column->alias};
                        break;
                }

                if($this->columnDefs->returnAsObject)
                    $data[$column->alias] = $value;
                else
                    $data[] = $value;
                
            }

            $result[] = $data;
        }

        return $result;
    }

    /* End Generating result */



    /* Querying */

    private function queryOrder($builder)
    {   

        $orderables         = $this->columnDefs->getOrderables();
        $oderColumnRequests = Request::get('order');
        
        if($oderColumnRequests)
        {
            foreach ($oderColumnRequests as $request)
            {
                $dir    = ($request['dir'] == 'desc') ? 'desc' : 'asc';
                $column = $orderables[$request['column']] ?? NULL;

                if( $column !== NULL)
                    $builder->orderBy($column, $dir);
            }
        }

    }

    private function queryFilterSearch($builder)
    {

        //individual column search (multi column search)
        $columnRequests = Request::get('columns');

        foreach ($columnRequests as $index => $request) 
        {
            $column              = $this->columnDefs->getSearchRequest($index, $request);
            $this->doQueryFilter = TRUE;
            
            if ($request['search']['regex'] != "false") {
                $search = explode(',', $request['search']['value']);
                $values = ['>=', '<=', '>', '<', '=', '!=', '<>'];
                if (isset($search[1]) && in_array($search[0], $values)) {
                    $builder->where($column . $search[0], $search[1]);
                } else {
                    $builder->where($column, $request['search']['value']);
                }

            } else {
                $builder->like($column, $request['search']['value']);
            }
        }

        //global search
        $searchRequest = Request::get('search');

        if($searchRequest['value'] != '')
        {
            $searchable = $this->columnDefs->getSearchable();

            if(! empty($searchable))
            {
                $this->doQueryFilter = TRUE;
                
                $builder->groupStart(); 
                foreach ($searchable as $column)
                    $builder->orLike(trim($column), $searchRequest['value']);
                
                $builder->groupEnd();
            }

        }

        $this->queryFilter($builder);
    }

    private function queryFilter($builder)
    {
        if($this->filter !== NULL)
        {
            $testBuilder = clone $builder;

            $callback = $this->filter;
            $callback($builder, Request::get());

            if($testBuilder != $builder)
                $this->doQueryFilter = TRUE;
        }
    }

    private function queryResult()
    {
        $builder = clone $this->builder;

        $this->queryOrder($builder);

        if(Request::get('length') != -1) 
            $builder->limit(Request::get('length'), Request::get('start'));

        $this->queryFilterSearch($builder);

        if($this->postQuery !== NULL)
        {
            $callback = $this->postQuery;
            $callback($builder);
        }

        return $builder->get()->getResult();
    }

    /* End Querying */
  

}   // End of DataTableQuery Class.

<?php


class Helper
{
    public function getCompaniesFromEndpoint(){
        $companies = [];

        $companies_data = file_get_contents("https://5f27781bf5d27e001612e057.mockapi.io/webprovise/companies");
        if(!$companies_data || empty(trim($companies_data))){
            return $companies;
        }
        $companies_data = json_decode($companies_data,true);
        if(!$companies_data || !is_array($companies_data)){
            return $companies;
        }

        foreach ($companies_data as $key => $cd) {
            if(!isset($cd['id']) || empty($cd['id'])){
                continue;
            }
            $cd['name'] = isset($cd['name']) && !empty($cd['name']) ? $cd['name'] : null;
            $cd['parentId'] = isset($cd['parentId']) && !empty($cd['parentId']) ? $cd['parentId'] : 0;

            $companies[$cd['id']] = new Company($cd['id'],$cd['name'],$cd['parentId']);
        }

        return $companies;
    }

    public function getTravelsFromEndpoint(){
        $travels = [];

        $travels_data = file_get_contents("https://5f27781bf5d27e001612e057.mockapi.io/webprovise/travels");
        if(!$travels_data || empty(trim($travels_data))){
            return $travels;
        }
        $travels_data = json_decode($travels_data,true);
        if(!$travels_data || !is_array($travels_data)){
            return $travels;
        }

        foreach ($travels_data as $key => $td) {
            if(!isset($td['companyId']) || empty($td['companyId'])){
                continue;
            }
            $td['price'] = isset($td['price']) ? floatval($td['price']) : null;
            
            $travels[] = new Travel($td['price'],$td['companyId']);
        }

        return $travels;
    }

    public function getCompaniesTree($companies = [], $travelsCostByCompany = [], $parent = null, $used = []){

        $branch = [];
        $parentId = !empty($parent->id) ? $parent->id : 0;
        
        foreach ($companies as $company) {
            if ($company->parentId == $parentId && !in_array($company->id,$used)) {
                $used[] = $company->id;
                if(!empty($companies[$parentId])){
                    $company->setParent($companies[$parentId]);
                }
                $children = $this->getCompaniesTree($companies, $travelsCostByCompany, $company,$used);
                if($children) {
                    $company->children = $children;
                }          
                $branch[] = $company;
            }
        }
        return $branch;
    }


    public function getTravelsCostByCompany($travels){
        $travelsCostByCompany = [];
        foreach ($travels as $key => $travel) {
            if(isset($travelsCostByCompany[$travel->companyId])){
                $travelsCostByCompany[$travel->companyId] += $travel->price;
            }else{
                $travelsCostByCompany[$travel->companyId] = $travel->price;
            }
        }

        return $travelsCostByCompany;
    }


    public function getCosts($companies,$travelsCostByCompany,$parent = null){
        foreach ($companies as $key => $company) {
            $company->cost = $travelsCostByCompany[$company->id];
            $company->passCostToParent($travelsCostByCompany[$company->id]);
            $this->getCosts($company->children,$travelsCostByCompany,$company);
        }    
    }


    public function cleanTree($companiesTree){
        foreach ($companiesTree as $key => $company) {
            if(!empty($company->parentId)){
                unset($companiesTree[$key]);
            }
        }
        return $companiesTree;
    }

    public function getOutputArray($companiesTree,&$output){
        
        if(!is_object($companiesTree) && !is_array($companiesTree)){
            $output = $companiesTree;
            return $output;
        }

        foreach ($companiesTree as $key => $value){
            if(!empty($value)){
                unset($value->parent);
                unset($value->parentId);
                $output[$key] = [];
                $this->getOutputArray($value, $output[$key]);
            }else{
                $output[$key] = $value;
            }
        }
        return $output;
    }
}

class Travel
{
    public $price;
    public $companyId;

    public function __construct($price,$companyId){
        $this->price = $price;
        $this->companyId = $companyId;
    }
}

class Company
{
    public $id;
    public $name;
    public $parentId;
    public $cost;
    public $children = [];
    public $parent;

    public function __construct($id,$name,$parentId){
        $this->id = $id;
        $this->name = $name;
        $this->parentId = $parentId;
    }

    public function setParent($parent){
        $this->parent = $parent;
    }

    public function passCostToParent($cost){
        if(!empty($this->parent)){
            $this->parent->cost += $cost;
            $this->parent->passCostToParent($cost);
        }
    }
}



class TestScript
{
    public function execute()
    {
        $start = microtime(true);
        
        $helper = new Helper();

        $travels = $helper->getTravelsFromEndpoint();
        $trabelsCostByCompany = $helper->getTravelsCostByCompany($travels);
        $companies = $helper->getCompaniesFromEndpoint();

        $companiesTree = $helper->cleanTree($helper->getCompaniesTree($companies,$trabelsCostByCompany));

        $helper->getCosts($companiesTree,$trabelsCostByCompany);

        $output = [];
        $helper->getOutputArray($companiesTree,$output);

        echo "<pre>" . print_r($output,true) . "</pre>";

        echo 'Total time: '.  (microtime(true) - $start);
    }
}

(new TestScript())->execute();
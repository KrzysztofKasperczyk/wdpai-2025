<?php  

require_once 'AppController.php';

class DashboardController extends AppController{

    public function index(?int $id){
        //TODO wyswietli wszystkie projekty z bazy danych

        return $this->render("dashboard");
    }
}
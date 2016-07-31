#Create a Base Migration File from current DB



When all goes well it will create a file under migrations called 001_create_base.php under your migrations folder


#To use:

1: Enable migrations and set version to 1;

2: Enable to write migration folder;

3: Copy WebbyMigration.php to your library folder (/application/library);

4: In controller:


    function make_base(){

        $this->load->library('WebbyMigration');

        // All Tables:

        $this->webbymigration->generate(NULL, '001_create_base');

        //Single Table:

        $this->webbymigration->generate('table_name', '20121031100537_add_table_name');
        $this->webbymigration->generate('table_name', '001_add_table_name');

    }
    

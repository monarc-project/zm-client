<?php

use Phinx\Migration\AbstractMigration;

class AddDeliveryModelRecordGdpr extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * http://docs.phinx.org/en/latest/migrations.html#the-abstractmigration-class
     *
     * The following commands can be used in this method and Phinx will
     * automatically reverse them when rolling back:
     *
     *    createTable
     *    renameTable
     *    addColumn
     *    renameColumn
     *    addIndex
     *    addForeignKey
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change()
    {
        $table = $this->table('deliveries_models');
        $this->query('INSERT INTO deliveries_models (category, description1, path1, creator, created_at, updater, updated_at, description2, description3, description4, path2, path3, path4, editable) VALUES (6, "Registre des activités de traitement", "./deliveries/cases/FR/6.docx", NULL , NULL, NULL, NULL, "Record of processing activities", "Verzeichnis von Verarbeitungstätigkeiten", "Register van de verwerkingsactiviteiten", "./deliveries/cases/EN/6.docx", "./deliveries/cases/DE/6.docx", "./deliveries/cases/NE/6.docx", false);');
        $this->query('INSERT INTO deliveries_models (category, description1, path1, creator, created_at, updater, updated_at, description2, description3, description4, path2, path3, path4, editable) VALUES (7, "Registre des activités de traitement", "./deliveries/cases/FR/7.docx", NULL , NULL, NULL, NULL, "Record of processing activities", "Verzeichnis von Verarbeitungstätigkeiten", "Register van de verwerkingsactiviteiten", "./deliveries/cases/EN/7.docx", "./deliveries/cases/DE/7.docx", "./deliveries/cases/NE/7.docx", false);');
    }
}

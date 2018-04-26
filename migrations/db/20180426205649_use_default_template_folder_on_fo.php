<?php

use Phinx\Migration\AbstractMigration;

class UseDefaultTemplateFolderOnFo extends AbstractMigration
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
        $this->query('DELETE FROM deliveries_models');
        $this->query('INSERT INTO deliveries_models (category, description1, path1, creator, created_at, updater, updated_at, description2, description3, description4, path2, path3, path4, editable) VALUES (1, "Validation de contexte", "./deliveries/cases/FR/1.docx", NULL , NULL, NULL, NULL, "Context validation", "Kontextüberprüfung", NULL, "./deliveries/cases/EN/1.docx", "./deliveries/cases/DE/1.docx", "./deliveries/cases/NE/1.docx", false), (2, "Validation du modèle"  , "./deliveries/cases/FR/2.docx", NULL , NULL, NULL, NULL, "Modelling validation", "Modellierungsüberprüfung", NULL, "./deliveries/cases/EN/2.docx", "./deliveries/cases/DE/2.docx", "./deliveries/cases/NE/2.docx", false), (3, "Rapport final", "./deliveries/cases/FR/3.docx", NULL, NULL, NULL, NULL, "Report risk assessment", "Risikobeurteilungsbericht", NULL, "./deliveries/cases/EN/3.docx", "./deliveries/cases/DE/3.docx", "./deliveries/cases/NE/3.docx", false), (4, "Plan dimplémentation" , "./deliveries/cases/FR/4.docx", NULL, NULL, NULL, NULL, "Implementation plan", "Implementierungsplan", "Implementatieplan", "./deliveries/cases/EN/4.docx", "./deliveries/cases/DE/4.docx", "./deliveries/cases/NE/4.docx", false);');
    }
}

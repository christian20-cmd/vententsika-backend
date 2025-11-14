    <?php

    use Illuminate\Database\Migrations\Migration;
    use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\Schema;

    return new class extends Migration
    {
        public function up()
        {
            Schema::create('vendeurs', function (Blueprint $table) {
                $table->id('idVendeur');
                $table->string('nom_entreprise');
                $table->text('adresse_entreprise');
                $table->text('description')->nullable();
                $table->string('logo_image')->nullable();
                $table->enum('statut_validation', ['en_attente', 'valide', 'refuse'])->default('en_attente');
                $table->decimal('commission_pourcentage', 5, 2)->default(0);
                $table->foreignId('idUtilisateur')->constrained('utilisateurs', 'idUtilisateur');
                $table->timestamps();
            });
        }

        public function down()
        {
            Schema::dropIfExists('vendeurs');
        }
    };

<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

exigerConnexion();

$utilisateurId = (int) $_SESSION['utilisateur_id'];
$estSuperAdmin = estSuperAdmin();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

$erreurs = [];
$succes = '';

$locationId = '';
$montant = '';
$periodeMois = date('Y-m');
$datePaiement = date('Y-m-d\TH:i');
$modePaiement = 'especes';
$reference = '';
$commentaire = '';

$modesPaiement = [
    'especes' => 'Espèces',
    'virement' => 'Virement bancaire',
    'mobile_money' => 'Mobile Money (Bankily)',
    'autre' => 'Autre'
];

/*
|--------------------------------------------------------------------------
| Récupérer les locations accessibles à l'utilisateur
|--------------------------------------------------------------------------
*/

try {
    $sqlLocations = "
        SELECT
            l.id,
            l.loyer_mensuel,
            l.date_debut,
            l.date_fin,
            loc.nom AS nom_locataire,
            loc.prenom AS prenom_locataire,
            a.numero AS numero_appartement,
            m.nom AS nom_maison,
            m.id AS maison_id
        FROM locations l
        INNER JOIN locataires loc
            ON loc.id = l.locataire_id
        INNER JOIN appartements a
            ON a.id = l.appartement_id
        INNER JOIN maisons m
            ON m.id = a.maison_id
        WHERE l.statut = 'active'
    ";

    $parametresLocations = [];

    /*
     * Un administrateur ne peut consulter que les locations
     * des maisons qui lui sont attribuées.
     */
    if (!$estSuperAdmin) {
        $sqlLocations .= "
            AND EXISTS (
                SELECT 1
                FROM affectations_admins aa
                WHERE aa.maison_id = m.id
                  AND aa.utilisateur_id = :utilisateur_id
            )
        ";

        $parametresLocations['utilisateur_id'] = $utilisateurId;
    }

    $sqlLocations .= "
        ORDER BY m.nom ASC, a.numero ASC, loc.nom ASC
    ";

    $requeteLocations = $pdo->prepare($sqlLocations);
    $requeteLocations->execute($parametresLocations);

    $locations = $requeteLocations->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log(
        'Erreur de chargement des locations pour un paiement : '
        . $e->getMessage()
    );

    http_response_code(500);
    exit('Impossible de charger les locations pour le moment.');
}

/*
|--------------------------------------------------------------------------
| Traitement du formulaire
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $locationId = trim($_POST['location_id'] ?? '');
    $montant = trim($_POST['montant'] ?? '');
    $periodeMois = trim($_POST['periode_mois'] ?? '');
    $datePaiement = trim($_POST['date_paiement'] ?? '');
    $modePaiement = trim($_POST['mode_paiement'] ?? '');
    $reference = trim($_POST['reference'] ?? '');
    $commentaire = trim($_POST['commentaire'] ?? '');

    /*
     * Vérification du jeton CSRF.
     */
    $tokenRecu = $_POST['csrf_token'] ?? '';

    if (
        !is_string($tokenRecu)
        || !hash_equals($csrfToken, $tokenRecu)
    ) {
        $erreurs[] = 'Votre session de formulaire a expiré. Rechargez la page et réessayez.';
    }

    /*
     * Vérification de l'identifiant de la location.
     */
    if (
        $locationId === ''
        || !ctype_digit($locationId)
        || (int) $locationId <= 0
    ) {
        $erreurs[] = 'Veuillez sélectionner une location valide.';
    }

    /*
     * Vérification du montant.
     */
    if (
        $montant === ''
        || !is_numeric($montant)
        || !is_finite((float) $montant)
        || (float) $montant <= 0
    ) {
        $erreurs[] = 'Le montant doit être un nombre supérieur à zéro.';
    } elseif (strlen($montant) > 20) {
        $erreurs[] = 'Le montant saisi est trop grand.';
    }

    /*
     * Vérification de la période au format AAAA-MM.
     */
    if (
        !preg_match('/^(\d{4})-(\d{2})$/', $periodeMois, $correspondances)
        || !checkdate(
            (int) $correspondances[2],
            1,
            (int) $correspondances[1]
        )
    ) {
        $erreurs[] = 'La période doit être un mois valide au format AAAA-MM.';
    }

    /*
     * Vérification de la date et de l'heure du paiement.
     */
    $dateValide = false;

    if (
        preg_match(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/',
            $datePaiement
        )
    ) {
        $dateObjet = DateTime::createFromFormat(
            '!Y-m-d\TH:i',
            $datePaiement
        );

        $dateValide = $dateObjet !== false
            && $dateObjet->format('Y-m-d\TH:i') === $datePaiement;
    }

    if (!$dateValide) {
        $erreurs[] = 'Veuillez saisir une date et une heure de paiement valides.';
    }

    /*
     * Vérification du mode de paiement.
     */
    if (!array_key_exists($modePaiement, $modesPaiement)) {
        $erreurs[] = 'Veuillez sélectionner un mode de paiement valide.';
    }

    /*
     * Vérification de la référence.
     */
    if (strlen($reference) > 100) {
        $erreurs[] = 'La référence ne doit pas dépasser 100 caractères.';
    }

    /*
     * Vérification de la longueur du commentaire.
     */
    if (strlen($commentaire) > 5000) {
        $erreurs[] = 'Le commentaire ne doit pas dépasser 5 000 caractères.';
    }

    /*
     * Enregistrer le paiement si les données sont valides.
     */
    if (empty($erreurs)) {

        try {
            $pdo->beginTransaction();

            /*
             * Recharger la location et vérifier les droits
             * dans la transaction avant d'enregistrer le paiement.
             */
            $sqlVerification = "
                SELECT
                    l.id,
                    l.loyer_mensuel,
                    l.statut,
                    a.maison_id
                FROM locations l
                INNER JOIN appartements a
                    ON a.id = l.appartement_id
                INNER JOIN maisons m
                    ON m.id = a.maison_id
                WHERE l.id = :location_id
                  AND l.statut = 'active'
            ";

            $parametresVerification = [
                'location_id' => (int) $locationId
            ];

            if (!$estSuperAdmin) {
                $sqlVerification .= "
                    AND EXISTS (
                        SELECT 1
                        FROM affectations_admins aa
                        WHERE aa.maison_id = m.id
                          AND aa.utilisateur_id = :utilisateur_id
                    )
                ";

                $parametresVerification['utilisateur_id'] = $utilisateurId;
            }

            $sqlVerification .= ' FOR UPDATE';

            $requeteVerification = $pdo->prepare($sqlVerification);
            $requeteVerification->execute($parametresVerification);

            $location = $requeteVerification->fetch(PDO::FETCH_ASSOC);

            if (!$location) {
                throw new RuntimeException(
                    'Cette location est introuvable, inactive ou vous n’êtes pas autorisé à y enregistrer un paiement.'
                );
            }

            /*
             * Insérer le paiement.
             * Les paiements sont conservés dans l'historique.
             */
            $sqlInsertion = "
                INSERT INTO paiements (
                    location_id,
                    enregistre_par,
                    montant,
                    periode_mois,
                    date_paiement,
                    mode_paiement,
                    reference,
                    statut,
                    commentaire
                ) VALUES (
                    :location_id,
                    :enregistre_par,
                    :montant,
                    :periode_mois,
                    :date_paiement,
                    :mode_paiement,
                    :reference,
                    'valide',
                    :commentaire
                )
            ";

            $requeteInsertion = $pdo->prepare($sqlInsertion);

            $requeteInsertion->execute([
                'location_id' => (int) $locationId,
                'enregistre_par' => $utilisateurId,
                'montant' => number_format(
                    (float) $montant,
                    2,
                    '.',
                    ''
                ),
                'periode_mois' => $periodeMois . '-01',
                'date_paiement' => str_replace('T', ' ', $datePaiement) . ':00',
                'mode_paiement' => $modePaiement,
                'reference' => $reference !== '' ? $reference : null,
                'commentaire' => $commentaire !== '' ? $commentaire : null
            ]);

            $pdo->commit();

            /*
             * Rediriger après l'enregistrement pour éviter
             * un nouvel enregistrement lors d'un rafraîchissement.
             */
            $_SESSION['message_succes'] = 'Le paiement a été enregistré avec succès.';

            header('Location: /ImmoGestion/paiements/index.php');
            exit;

        } catch (RuntimeException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $erreurs[] = $e->getMessage();

        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                'Erreur lors de l’enregistrement d’un paiement : '
                . $e->getMessage()
            );

            $erreurs[] = 'Une erreur est survenue pendant l’enregistrement du paiement. Vérifiez les informations et réessayez.';
        }
    }
}

/*
|--------------------------------------------------------------------------
| Fonction d'échappement HTML
|--------------------------------------------------------------------------
*/

function echapperPaiement($valeur): string
{
    return htmlspecialchars(
        (string) $valeur,
        ENT_QUOTES,
        'UTF-8'
    );
}

?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>
<?php require_once __DIR__ . '/../includes/navbar.php'; ?>

<div class="app-layout">
    <main class="main-content">

        <div class="page-content">

            <div class="topbar">
                <div>
                    <h1>Enregistrer un paiement</h1>
                    <p>Enregistrez un règlement de loyer.</p>
                </div>

                <a
                    href="/ImmoGestion/paiements/index.php"
                    class="btn btn-secondary"
                >
                    Retour aux paiements
                </a>
            </div>

            <?php if (!empty($erreurs)): ?>
                <div class="alert alert-danger">
                    <strong>Impossible d'enregistrer le paiement :</strong>

                    <ul>
                        <?php foreach ($erreurs as $erreur): ?>
                            <li><?= echapperPaiement($erreur) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (empty($locations)): ?>
                <div class="alert alert-danger">
                    Aucune location active accessible n'est disponible pour enregistrer un paiement.
                </div>
            <?php else: ?>

                <div class="panel">

                    <form method="POST" action="">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= echapperPaiement($csrfToken) ?>"
                        >

                        <div class="form-group">
                            <label for="location_id">
                                Location / Locataire
                            </label>

                            <select
                                name="location_id"
                                id="location_id"
                                class="form-control"
                                required
                            >
                                <option value="">-- Sélectionner une location --</option>

                                <?php foreach ($locations as $locationOption): ?>
                                    <option
                                        value="<?= (int) $locationOption['id'] ?>"
                                        data-loyer="<?= echapperPaiement($locationOption['loyer_mensuel']) ?>"
                                        <?= (string) $locationId === (string) $locationOption['id'] ? 'selected' : '' ?>
                                    >
                                        <?= echapperPaiement($locationOption['nom_maison']) ?>
                                        — Appartement
                                        <?= echapperPaiement($locationOption['numero_appartement']) ?>
                                        — <?= echapperPaiement($locationOption['nom_locataire'] . ' ' . $locationOption['prenom_locataire']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="montant">
                                Montant reçu (MRU)
                            </label>

                            <input
                                type="number"
                                name="montant"
                                id="montant"
                                class="form-control"
                                min="0.01"
                                step="0.01"
                                value="<?= echapperPaiement($montant) ?>"
                                required
                            >

                            <small>
                                Le loyer mensuel sera proposé automatiquement.
                                Vous pouvez modifier le montant pour enregistrer
                                un paiement partiel.
                            </small>
                        </div>

                        <div class="form-group">
                            <label for="periode_mois">
                                Mois concerné
                            </label>

                            <input
                                type="month"
                                name="periode_mois"
                                id="periode_mois"
                                class="form-control"
                                value="<?= echapperPaiement($periodeMois) ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="date_paiement">
                                Date et heure du paiement
                            </label>

                            <input
                                type="datetime-local"
                                name="date_paiement"
                                id="date_paiement"
                                class="form-control"
                                value="<?= echapperPaiement($datePaiement) ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="mode_paiement">
                                Mode de paiement
                            </label>

                            <select
                                name="mode_paiement"
                                id="mode_paiement"
                                class="form-control"
                                required
                            >
                                <?php foreach ($modesPaiement as $valeur => $libelle): ?>
                                    <option
                                        value="<?= echapperPaiement($valeur) ?>"
                                        <?= $modePaiement === $valeur ? 'selected' : '' ?>
                                    >
                                        <?= echapperPaiement($libelle) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="reference">
                                Référence du paiement (facultatif)
                            </label>

                            <input
                                type="text"
                                name="reference"
                                id="reference"
                                class="form-control"
                                maxlength="100"
                                value="<?= echapperPaiement($reference) ?>"
                                placeholder="Ex. : référence de transaction"
                            >
                        </div>

                        <div class="form-group">
                            <label for="commentaire">
                                Commentaire (facultatif)
                            </label>

                            <textarea
                                name="commentaire"
                                id="commentaire"
                                class="form-control"
                                rows="4"
                                maxlength="5000"
                                placeholder="Informations complémentaires..."
                            ><?= echapperPaiement($commentaire) ?></textarea>
                        </div>

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            Enregistrer le paiement
                        </button>

                        <a
                            href="/ImmoGestion/paiements/index.php"
                            class="btn btn-secondary"
                        >
                            Annuler
                        </a>

                    </form>

                </div>

            <?php endif; ?>

        </div>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const selectLocation = document.getElementById('location_id');
    const champMontant = document.getElementById('montant');

    if (!selectLocation || !champMontant) {
        return;
    }

    selectLocation.addEventListener('change', function () {
        const option = this.options[this.selectedIndex];

        if (option && option.dataset.loyer) {
            champMontant.value = option.dataset.loyer;
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

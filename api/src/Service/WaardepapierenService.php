<?php

namespace App\Service;

use App\Entity\Entity;
use App\Entity\ObjectEntity;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\QrCode;
use Endroid\QrCodeBundle\Response\QrCodeResponse;
use Dompdf\Dompdf;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\RS512;
use Jose\Component\Signature\Serializer\CompactSerializer;

class WaardepapierenService
{
    private EntityManagerInterface $entityManager;
    private TranslationService $translationService;
    private ObjectEntityService $objectEntityService;
    private EavService $eavService;
    private SynchronizationService $synchronizationService;
    private array $configuration;
    private array $data;
    private QrCode $qrCode;

    /**
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        ObjectEntityService $objectEntityService,
        SynchronizationService $synchronizationService,
        QrCode $qrCode
    ) {
        $this->entityManager = $entityManager;
        $this->objectEntityService = $objectEntityService;
        $this->synchronizationService = $synchronizationService;
        $this->qrCode = $qrCode;

        $this->objectEntityRepo = $this->entityManager->getRepository(ObjectEntity::class);
        $this->entityRepo = $this->entityManager->getRepository(Entity::class);
    }

    /**
     * This function creates a QR code for the given claim.
     *
     * @param array $certificate The certificate object
     *
     * @return Certificate The modified certificate object
     */
    public function createImage(array $certificate = [])
    {

        // Then we need to render the QR code
        $qrCode = $this->qrCode->create($certificate['jwt'], [
            'size'  => 1000,
            'margin' => 1,
            'writer' => 'png',
        ]);
        // $response = new QrCodeResponse($qrCode);

        // And finnaly we need to set the result on the certificate resource
        $certificate['image'] = 'data:image/png;base64,' . base64_encode($qrCode);

        return $certificate;
    }

    /**
     * This function generates a claim based on the w3c structure.
     *
     * @param array       $data        The data used to create the claim
     * @param array $certificate The certificate object
     *
     * @throws \Exception
     *
     * @return array The generated claim
     */
    public function w3cClaim(array $data, array $certificate)
    {
        $now = new \DateTime('now', new \DateTimeZone('Europe/Amsterdam'));
        $array = [];
        $array['@context'] = ['https://www.w3.org/2018/credentials/v1', 'https://www.w3.org/2018/credentials/examples/v1'];
        $array['id'] = $certificate['id'];
        $array['type'] = ['VerifiableCredential', $certificate['type']];
        $array['issuer'] = $certificate['organization'];
        $array['inssuanceDate'] = $now->format('H:i:s d-m-Y');
        $array['credentialSubject']['id'] = $certificate['personObject']['burgerservicenummer'] ?? $certificate['organization'];
        foreach ($data as $key => $value) {
            $array['credentialSubject'][$key] = $value;
        }
        $array['proof'] = $this->createProof($certificate, $array);

        return $array;
    }


    /**
     * This function creates a proof.
     *
     * @param array $certificate the certificate object
     * @param array       $data        the data that gets stored in the jws token of the proof
     *
     * @return array proof
     */
    public function createProof(array $certificate, array $data)
    {
        $proof = [];
        $proof['type'] = 'RsaSignature';
        $proof['created'] = date('H:i:s d-m-Y', filectime("cert/{" . $certificate['organization'] . "}.pem"));
        $proof['proofPurpose'] = 'assertionMethode';
        $proof['verificationMethod'] = $this->requestStack->getCurrentRequest()->getSchemeAndHttpHost() . "/cert/{" . $certificate['organization'] . "}.pem";
        $proof['jws'] = $this->createJWS($certificate, $data['credentialSubject']);

        return $proof;
    }

    /**
     * This function generates a JWS token with the RS512 algorithm.
     *
     * @param array $certificate the certificate object
     * @param array       $data        the data that gets stored in the jws token
     *
     * @return string Generated JWS token.
     */
    public function createJWS(array $certificate, array $data)
    {
        $algorithmManager = new AlgorithmManager([
            new RS512(),
        ]);
        $jwk = JWKFactory::createFromKeyFile(
            "../cert/{" . $certificate['organization'] . "}.pem"
        );
        $jwsBuilder = new \Jose\Component\Signature\JWSBuilder($algorithmManager);
        $payload = json_encode([
            'iat'  => time(),
            'nbf'  => time(),
            'exp'  => time() + 3600,
            // 'crt'  => $this->commonGroundService->cleanUrl(['component' => 'frontend', 'type' => 'claims/public_keys', 'id' => $certificate['organization']]),
            'iss'  => $certificate['id'],
            'aud'  => $certificate['personObject']['burgerservicenummer'] ?? $certificate['organizaiton'],
            'data' => $data,
        ]);
        $jws = $jwsBuilder
            ->create()
            ->withPayload($payload)
            ->addSignature($jwk, ['alg' => 'RS512'])
            ->build();
        $serializer = new CompactSerializer();

        return $serializer->serialize($jws, 0);
    }

    /**
     * This function creates the (pdf) document for a given certificate type.
     *
     * @param array $certificate The certificate object
     *
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     *
     * @return array The modified certificate object
     */
    public function createDocument(array $certificate)
    {
        $data = [
            'qr'     => $certificate['image'],
            'claim'  => $certificate['claim'],
            'person' => $certificate['personObject'] ?? null,
            'base'   => '/organizations/' . $certificate['organization'] . '.html.twig',
        ];

        // if ($certificate['type'] == 'historisch_uittreksel_basis_registratie_personen') {
        //     $data['verblijfplaatshistorie'] = $this->commonGroundService->getResourceList(['component' => 'brp', 'type' => 'ingeschrevenpersonen', 'id' => $certificate->getPersonObject()['burgerservicenummer'].'/verblijfplaatshistorie'])['_embedded']['verblijfplaatshistorie'];
        // }

        // First we need the HTML  for the template
        // $html = $this->twig->render('certificates/'.$certificate->getType().'.html.twig', array_filter($data));
        $html = $this->twig->render('certificates/' . $certificate['type'] . '.html.twig', array_filter($data));

        // Then we need to render the template
        $dompdf = new DOMPDF();
        $dompdf->loadHtml($html);
        $dompdf->render();

        // And finnaly we need to set the result on the certificate resource
        $certificate['document'] = 'data:application/pdf;base64,' . base64_encode($dompdf->output());

        return $certificate;
    }

    /**
     * Creates or updates a Certificate.
     *
     * @param array $data          Data from the handler where the xxllnc casetype is in.
     * @param array $configuration Configuration from the Action where the ZaakType entity id is stored in.
     *
     * @return array $this->data Data which we entered the function with
     */
    public function waardepapierenHandler(array $data, array $configuration): array
    {
        $certificate = $data['response'];
        $this->configuration = $configuration;


        // 1. Check of type waardepapier valid is   


        // 2. Haal persoonsgegevens op bij pink brp 


        // 3. Vul data van certificate in op basis van type en persoonsgegevens




        var_dump('test waardepapieren plugin');
        die;


        return $this->data;
    }
}

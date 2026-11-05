<?php
namespace Controllers;

use Core\Controller;
use Models\Resi;
use Models\Tracking;
use Models\Manifest;

/**
 * Controller CRUD Checkpoint Tracking Perjalanan & Status Serah Terima (POD)
 */
class TrackingController extends Controller
{
    private Resi $resiModel;
    private Tracking $trackingModel;
    private Manifest $manifestModel;

    public function __construct()
    {
        $this->resiModel = new Resi();
        $this->trackingModel = new Tracking();
        $this->manifestModel = new Manifest();
    }

    /**
     * Halaman Dashboard Tracking & Serah Terima
     */
    public function index(?string $noResi = null): void
    {
        $user = $this->requireAuth();

        if (empty($noResi)) {
            $noResi = $_GET['resi'] ?? 'KT-CRB-261030-0047';
        }

        $resi = $this->resiModel->getDetail($noResi);
        if (!$resi) {
            // Ambil resi pertama jika nomor tidak ditemukan
            $resi = $this->resiModel->all('*', 'id DESC')[0] ?? null;
            if ($resi) {
                $resi = $this->resiModel->getDetail($resi['id']);
            }
        }

        $checkpoints = [];
        if ($resi) {
            $checkpoints = $this->trackingModel->getCheckpointsByResi($resi['id']);
        }

        $allResi = $this->resiModel->getAllWithRute(null, 30);

        $this->view('tracking/index', [
            'user' => $user,
            'resi' => $resi,
            'checkpoints' => $checkpoints,
            'allResi' => $allResi,
            'pageTitle' => 'Tracking & Serah Terima - KargoTrack'
        ]);
    }

    /**
     * Tambah Checkpoint Perjalanan Baru
     */
    public function storeCheckpoint(): void
    {
        $user = $this->requireAuth();
        $resiId = (int)($_POST['resi_id'] ?? 0);

        $resi = $this->resiModel->find($resiId);
        if (!$resi) {
            $this->json(['success' => false, 'message' => 'Resi tidak ditemukan.'], 404);
        }

        $status = trim($_POST['status'] ?? '');
        $lokasi = trim($_POST['lokasi'] ?? '');
        $waktu = trim($_POST['waktu'] ?? date('Y-m-d H:i:s'));
        $namaPenerima = trim($_POST['nama_penerima'] ?? '');
        $keterangan = trim($_POST['keterangan'] ?? '');

        if (empty($status) || empty($lokasi)) {
            $this->json(['success' => false, 'message' => 'Status dan lokasi checkpoint wajib diisi.'], 422);
        }

        // Handle Upload Foto Bukti Serah Terima (POD)
        $fotoBuktiName = null;
        if (isset($_FILES['foto_bukti']) && $_FILES['foto_bukti']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['foto_bukti'];
            $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, $allowedExts)) {
                $this->json(['success' => false, 'message' => 'Format foto harus JPG, PNG, atau WEBP.'], 422);
            }

            if ($file['size'] > 5 * 1024 * 1024) { // Maks 5 MB
                $this->json(['success' => false, 'message' => 'Ukuran file foto maksimal 5 MB.'], 422);
            }

            // Simpan ke root /uploads/pod/ agar dapat diakses via URL /uploads/pod/xxx
            $uploadDir = dirname(__DIR__) . '/uploads/pod/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            // Mirror ke public/uploads/pod/ sebagai fallback jika akses via /public/
            $publicUploadDir = dirname(__DIR__) . '/public/uploads/pod/';
            if (!is_dir($publicUploadDir)) {
                mkdir($publicUploadDir, 0777, true);
            }

            $fotoBuktiName = 'pod_' . $resi['id'] . '_' . time() . '.' . $ext;
            $tmpPath = $file['tmp_name'];
            move_uploaded_file($tmpPath, $uploadDir . $fotoBuktiName);
            // Duplikat ke public/ agar konsisten pada semua konfigurasi server
            copy($uploadDir . $fotoBuktiName, $publicUploadDir . $fotoBuktiName);
        }

        // Handle Tanda Tangan Digital (Base64 dari Canvas)
        $ttdDigital = $_POST['ttd_digital'] ?? null;
        if (empty($ttdDigital) || strlen($ttdDigital) < 50) {
            $ttdDigital = null;
        }

        $checkpointData = [
            'status' => $status,
            'lokasi' => $lokasi,
            'waktu' => $waktu,
            'nama_penerima' => !empty($namaPenerima) ? $namaPenerima : null,
            'petugas' => ($user['nama'] ?? 'Rizqi') . ' - Petugas',
            'keterangan' => $keterangan,
            'foto_bukti' => $fotoBuktiName,
            'ttd_digital' => $ttdDigital
        ];

        $cpId = $this->trackingModel->addCheckpoint($resiId, $checkpointData);

        if ($this->isAjax()) {
            $this->json([
                'success' => true,
                'message' => "Checkpoint '{$status}' berhasil ditambahkan.",
                'checkpoint_id' => $cpId,
                'no_resi' => $resi['no_resi']
            ]);
        }

        $this->setFlash('success', "Checkpoint baru berhasil dicatat.");
        $this->redirect('tracking?resi=' . urlencode($resi['no_resi']));
    }

    /**
     * Pelacakan Publik Tanpa Login (Cek Resi Tanpa Login)
     */
    public function publicTrack(?string $noResi = null): void
    {
        if (empty($noResi)) {
            $noResi = trim($_GET['resi'] ?? '');
        }

        $resi = null;
        $checkpoints = [];

        if (!empty($noResi)) {
            $resi = $this->resiModel->getDetail($noResi);
            if ($resi) {
                $checkpoints = $this->trackingModel->getCheckpointsByResi($resi['id']);
            }
        }

        $this->view('tracking/public', [
            'noResi' => $noResi,
            'resi' => $resi,
            'checkpoints' => $checkpoints,
            'pageTitle' => 'Lacak Resi Kargo Publik - KargoTrack'
        ], null); // Tanpa layout dashboard admin
    }

    /**
     * JSON Live API Tracking Data
     */
    public function apiTrack(string $noResi): void
    {
        $cleanResi = trim($noResi);
        $resi = $this->resiModel->getDetail($cleanResi);

        if (!$resi) {
            $this->json(['success' => false, 'message' => "Nomor resi {$cleanResi} tidak ditemukan di sistem."], 404);
        }

        $checkpoints = $this->trackingModel->getCheckpointsByResi($resi['id']);

        $this->json([
            'success' => true,
            'resi' => $resi,
            'checkpoints' => $checkpoints
        ]);
    }
}


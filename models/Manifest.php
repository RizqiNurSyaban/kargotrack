<?php
namespace Models;

use Core\Model;

/**
 * Model Manifest Muatan Armada Truk Kargo Antar-Kota
 */
class Manifest extends Model
{
    protected string $table = 'manifest';

    /**
     * Ambil data manifest beserta relasi wilayah kota asal dan tujuan
     */
    public function getAllWithDetails(): array
    {
        $sql = "SELECT 
                    m.*,
                    w_asal.nama_kota as asal_nama,
                    w_asal.kode_kota as asal_kode,
                    w_tujuan.nama_kota as tujuan_nama,
                    w_tujuan.kode_kota as tujuan_kode,
                    COUNT(r.id) as total_resi,
                    COALESCE(SUM(r.jumlah_koli), 0) as total_koli,
                    COALESCE(SUM(r.berat_dikenakan), 0) as total_berat_kg
                FROM `{$this->table}` m
                JOIN `wilayah` w_asal ON m.kota_asal_id = w_asal.id
                JOIN `wilayah` w_tujuan ON m.kota_tujuan_id = w_tujuan.id
                LEFT JOIN `resi` r ON r.manifest_id = m.id
                GROUP BY m.id
                ORDER BY m.id DESC";

        return $this->query($sql);
    }

    /**
     * Ambil daftar resi yang tergabung dalam manifest tertentu
     */
    public function getResiInManifest(int $manifestId): array
    {
        $sql = "SELECT r.*, rt.kode_rute 
                FROM `resi` r
                JOIN `rute` rt ON r.rute_id = rt.id
                WHERE r.manifest_id = :mid
                ORDER BY r.id ASC";

        return $this->query($sql, [':mid' => $manifestId]);
    }

    /**
     * Generate Nomor Manifest Baru (MF-XXXX)
     */
    public function generateNoManifest(): string
    {
        $sql = "SELECT no_manifest FROM `{$this->table}` ORDER BY id DESC LIMIT 1";
        $last = $this->db->query($sql)->fetchColumn();
        if ($last && preg_match('/MF-(\d+)/', $last, $m)) {
            $next = (int)$m[1] + 1;
        } else {
            $next = 232;
        }
        return 'MF-' . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Berangkatkan Truk Manifest
     * Otomatis mengupdate status semua paket di manifest menjadi "Dalam perjalanan"
     * dan menambahkan tracking checkpoint "Dimuat ke truk [no_polisi] · Manifest [no_manifest]"
     */
    public function berangkatkan(int $manifestId, string $petugas = 'Petugas Dispatch'): bool
    {
        $manifest = $this->find($manifestId);
        if (!$manifest) return false;

        $this->update($manifestId, ['status' => 'Dalam Perjalanan']);

        $resiList = $this->getResiInManifest($manifestId);
        $trackingModel = new Tracking();
        $resiModel = new Resi();

        $waktu = date('Y-m-d H:i:s');
        $statusText = "Dimuat ke truk {$manifest['no_polisi']}";
        $keterangan = "Manifest {$manifest['no_manifest']} keberangkatan armada";

        foreach ($resiList as $item) {
            $resiModel->updateStatus($item['id'], 'Dalam perjalanan');
            $trackingModel->create([
                'resi_id' => $item['id'],
                'status' => $statusText,
                'lokasi' => 'Gudang Cirebon',
                'waktu' => $waktu,
                'petugas' => $manifest['nama_sopir'] . ' (Sopir)',
                'keterangan' => $keterangan
            ]);
        }

        return true;
    }
}


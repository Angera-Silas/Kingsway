<?php
namespace App\API\Modules\reports;
use App\API\Includes\BaseAPI;

class CommunicationReportManager extends BaseAPI
{
    public function getCommunicationsStats($filters = [])
    {
        // Count communications by type and status
        try {
            $sql = "SELECT type, status, COUNT(*) as total
                    FROM communications
                    GROUP BY type, status
                    ORDER BY total DESC";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getParentPortalStats($filters = [])
    {
        // Count parent portal messages by status (stored in communications, type='internal')
        try {
            $sql = "SELECT status, COUNT(*) as total
                    FROM communications
                    WHERE type = 'internal'
                    GROUP BY status
                    ORDER BY total DESC";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getForumActivityStats($filters = [])
    {
        // Count forum threads and posts by type
        try {
            $sql = "SELECT forum_type, COUNT(*) as thread_count
                    FROM forum_threads
                    GROUP BY forum_type";
            $stmt1 = $this->db->query($sql);
            $threads = $stmt1->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            $threads = [];
        }

        try {
            $sql2 = "SELECT author_type, COUNT(*) as post_count
                     FROM forum_posts
                     GROUP BY author_type";
            $stmt2 = $this->db->query($sql2);
            $posts = $stmt2->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            $posts = [];
        }

        return ['threads' => $threads, 'posts' => $posts];
    }

    public function getAnnouncementReach($filters = [])
    {
        // Count announcements and their read status
        try {
            $sql = "SELECT a.id, a.title, COUNT(av.id) as total_recipients,
                           COUNT(DISTINCT av.viewer_id) as read_count,
                           ROUND(
                               COUNT(DISTINCT av.viewer_id) / NULLIF(COUNT(av.id), 0) * 100,
                               2
                           ) AS read_rate
                    FROM announcements_bulletin a
                    LEFT JOIN announcement_views av ON av.announcement_id = a.id
                    GROUP BY a.id, a.title
                    ORDER BY total_recipients DESC
                    LIMIT 100";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }
}

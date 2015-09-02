<?php namespace DominoPOS\SymmetricDS\Model;

/**
 * Class Channel
 * @package DominoPOS\SymmetricDS
 *
 * @property string channel_id
 * @property string processing_order
 * @property string max_batch_size
 * @property string max_batch_to_send
 * @property string max_data_to_route
 * @property string extract_period_millis
 * @property string enabled
 * @property string use_old_data_to_route
 * @property string use_row_data_to_route
 * @property string use_pk_data_to_route
 * @property string reload_flag
 * @property string file_sync_flag
 * @property string contains_big_lob
 * @property string batch_algorithm
 * @property string data_loader_type
 * @property string description
 * @property string create_time
 * @property string last_update_by
 * @property string last_update_time
 */
class Channel extends Base {
    protected $table = 'channel';

    protected $primaryKey = 'channel_id';
}

<?php

declare(strict_types=1);

namespace Plugin\ProvincesVN\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Plugin\ProvincesVN\Repositories\ProvincesVNRepo;

class ProvincesVNSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('zones') || ! Schema::hasTable('provinces_vn_meta') || ! Schema::hasTable('provinces_vn_wards')) {
            return;
        }

        ProvincesVNRepo::seedVietnamDataWithOverwrite($this->data());
    }

    private function data(): array
    {
        return array(
  0 =>
  array(
    'matinhBNV' => '01',
    'matinhTMS' => '101',
    'tentinhmoi' => 'Thành phố Hà Nội',
    'phuongxa' =>
    array(
      0 =>
      array(
        'maphuongxa' => 10105001,
        'tenphuongxa' => 'Phường Hoàn Kiếm',
      ),
      1 =>
      array(
        'maphuongxa' => 10105002,
        'tenphuongxa' => 'Phường Cửa Nam',
      ),
      2 =>
      array(
        'maphuongxa' => 10101003,
        'tenphuongxa' => 'Phường Ba Đình',
      ),
      3 =>
      array(
        'maphuongxa' => 10101004,
        'tenphuongxa' => 'Phường Ngọc Hà',
      ),
      4 =>
      array(
        'maphuongxa' => 10101005,
        'tenphuongxa' => 'Phường Giảng Võ',
      ),
      5 =>
      array(
        'maphuongxa' => 10107006,
        'tenphuongxa' => 'Phường Hai Bà Trưng',
      ),
      6 =>
      array(
        'maphuongxa' => 10107007,
        'tenphuongxa' => 'Phường Vĩnh Tuy',
      ),
      7 =>
      array(
        'maphuongxa' => 10107008,
        'tenphuongxa' => 'Phường Bạch Mai',
      ),
      8 =>
      array(
        'maphuongxa' => 10109009,
        'tenphuongxa' => 'Phường Đống Đa',
      ),
      9 =>
      array(
        'maphuongxa' => 10109010,
        'tenphuongxa' => 'Phường Kim Liên',
      ),
      10 =>
      array(
        'maphuongxa' => 10109011,
        'tenphuongxa' => 'Phường Văn Miếu - Quốc Tử Giám',
      ),
      11 =>
      array(
        'maphuongxa' => 10109012,
        'tenphuongxa' => 'Phường Láng',
      ),
      12 =>
      array(
        'maphuongxa' => 10109013,
        'tenphuongxa' => 'Phường Ô Chợ Dừa',
      ),
      13 =>
      array(
        'maphuongxa' => 10103014,
        'tenphuongxa' => 'Phường Hồng Hà',
      ),
      14 =>
      array(
        'maphuongxa' => 10108015,
        'tenphuongxa' => 'Phường Lĩnh Nam',
      ),
      15 =>
      array(
        'maphuongxa' => 10108016,
        'tenphuongxa' => 'Phường Hoàng Mai',
      ),
      16 =>
      array(
        'maphuongxa' => 10108017,
        'tenphuongxa' => 'Phường Vĩnh Hưng',
      ),
      17 =>
      array(
        'maphuongxa' => 10108018,
        'tenphuongxa' => 'Phường Tương Mai',
      ),
      18 =>
      array(
        'maphuongxa' => 10108019,
        'tenphuongxa' => 'Phường Định Công',
      ),
      19 =>
      array(
        'maphuongxa' => 10123020,
        'tenphuongxa' => 'Phường Hoàng Liệt',
      ),
      20 =>
      array(
        'maphuongxa' => 10108021,
        'tenphuongxa' => 'Phường Yên Sở',
      ),
      21 =>
      array(
        'maphuongxa' => 10111022,
        'tenphuongxa' => 'Phường Thanh Xuân',
      ),
      22 =>
      array(
        'maphuongxa' => 10111023,
        'tenphuongxa' => 'Phường Khương Đình',
      ),
      23 =>
      array(
        'maphuongxa' => 10111024,
        'tenphuongxa' => 'Phường Phương Liệt',
      ),
      24 =>
      array(
        'maphuongxa' => 10113025,
        'tenphuongxa' => 'Phường Cầu Giấy',
      ),
      25 =>
      array(
        'maphuongxa' => 10113026,
        'tenphuongxa' => 'Phường Nghĩa Đô',
      ),
      26 =>
      array(
        'maphuongxa' => 10113027,
        'tenphuongxa' => 'Phường Yên Hoà',
      ),
      27 =>
      array(
        'maphuongxa' => 10103028,
        'tenphuongxa' => 'Phường Tây Hồ',
      ),
      28 =>
      array(
        'maphuongxa' => 10157029,
        'tenphuongxa' => 'Phường Phú Thượng',
      ),
      29 =>
      array(
        'maphuongxa' => 10157030,
        'tenphuongxa' => 'Phường Tây Tựu',
      ),
      30 =>
      array(
        'maphuongxa' => 10157031,
        'tenphuongxa' => 'Phường Phú Diễn',
      ),
      31 =>
      array(
        'maphuongxa' => 10157032,
        'tenphuongxa' => 'Phường Xuân Đỉnh',
      ),
      32 =>
      array(
        'maphuongxa' => 10157033,
        'tenphuongxa' => 'Phường Đông Ngạc',
      ),
      33 =>
      array(
        'maphuongxa' => 10157034,
        'tenphuongxa' => 'Phường Thượng Cát',
      ),
      34 =>
      array(
        'maphuongxa' => 10155035,
        'tenphuongxa' => 'Phường Từ Liêm',
      ),
      35 =>
      array(
        'maphuongxa' => 10155036,
        'tenphuongxa' => 'Phường Xuân Phương',
      ),
      36 =>
      array(
        'maphuongxa' => 10155037,
        'tenphuongxa' => 'Phường Tây Mỗ',
      ),
      37 =>
      array(
        'maphuongxa' => 10155038,
        'tenphuongxa' => 'Phường Đại Mỗ',
      ),
      38 =>
      array(
        'maphuongxa' => 10106039,
        'tenphuongxa' => 'Phường Long Biên',
      ),
      39 =>
      array(
        'maphuongxa' => 10106040,
        'tenphuongxa' => 'Phường Bồ Đề',
      ),
      40 =>
      array(
        'maphuongxa' => 10106041,
        'tenphuongxa' => 'Phường Việt Hưng',
      ),
      41 =>
      array(
        'maphuongxa' => 10106042,
        'tenphuongxa' => 'Phường Phúc Lợi',
      ),
      42 =>
      array(
        'maphuongxa' => 10127043,
        'tenphuongxa' => 'Phường Hà Đông',
      ),
      43 =>
      array(
        'maphuongxa' => 10127044,
        'tenphuongxa' => 'Phường Dương Nội',
      ),
      44 =>
      array(
        'maphuongxa' => 10127045,
        'tenphuongxa' => 'Phường Yên Nghĩa',
      ),
      45 =>
      array(
        'maphuongxa' => 10127046,
        'tenphuongxa' => 'Phường Phú Lương',
      ),
      46 =>
      array(
        'maphuongxa' => 10127047,
        'tenphuongxa' => 'Phường Kiến Hưng',
      ),
      47 =>
      array(
        'maphuongxa' => 10123048,
        'tenphuongxa' => 'Xã Thanh Trì',
      ),
      48 =>
      array(
        'maphuongxa' => 10123049,
        'tenphuongxa' => 'Xã Đại Thanh',
      ),
      49 =>
      array(
        'maphuongxa' => 10123050,
        'tenphuongxa' => 'Xã Nam Phù',
      ),
      50 =>
      array(
        'maphuongxa' => 10123051,
        'tenphuongxa' => 'Xã Ngọc Hồi',
      ),
      51 =>
      array(
        'maphuongxa' => 10123052,
        'tenphuongxa' => 'Phường Thanh Liệt',
      ),
      52 =>
      array(
        'maphuongxa' => 10143053,
        'tenphuongxa' => 'Xã Thượng Phúc',
      ),
      53 =>
      array(
        'maphuongxa' => 10143054,
        'tenphuongxa' => 'Xã Thường Tín',
      ),
      54 =>
      array(
        'maphuongxa' => 10143055,
        'tenphuongxa' => 'Xã Chương Dương',
      ),
      55 =>
      array(
        'maphuongxa' => 10143056,
        'tenphuongxa' => 'Xã Hồng Vân',
      ),
      56 =>
      array(
        'maphuongxa' => 10149057,
        'tenphuongxa' => 'Xã Phú Xuyên',
      ),
      57 =>
      array(
        'maphuongxa' => 10149058,
        'tenphuongxa' => 'Xã Phượng Dực',
      ),
      58 =>
      array(
        'maphuongxa' => 10149059,
        'tenphuongxa' => 'Xã Chuyên Mỹ',
      ),
      59 =>
      array(
        'maphuongxa' => 10149060,
        'tenphuongxa' => 'Xã Đại Xuyên',
      ),
      60 =>
      array(
        'maphuongxa' => 10141061,
        'tenphuongxa' => 'Xã Thanh Oai',
      ),
      61 =>
      array(
        'maphuongxa' => 10141062,
        'tenphuongxa' => 'Xã Bình Minh',
      ),
      62 =>
      array(
        'maphuongxa' => 10141063,
        'tenphuongxa' => 'Xã Tam Hưng',
      ),
      63 =>
      array(
        'maphuongxa' => 10141064,
        'tenphuongxa' => 'Xã Dân Hoà',
      ),
      64 =>
      array(
        'maphuongxa' => 10147065,
        'tenphuongxa' => 'Xã Vân Đình',
      ),
      65 =>
      array(
        'maphuongxa' => 10147066,
        'tenphuongxa' => 'Xã Ứng Thiên',
      ),
      66 =>
      array(
        'maphuongxa' => 10147067,
        'tenphuongxa' => 'Xã Hoà Xá',
      ),
      67 =>
      array(
        'maphuongxa' => 10147068,
        'tenphuongxa' => 'Xã Ứng Hoà',
      ),
      68 =>
      array(
        'maphuongxa' => 10145069,
        'tenphuongxa' => 'Xã Mỹ Đức',
      ),
      69 =>
      array(
        'maphuongxa' => 10145070,
        'tenphuongxa' => 'Xã Hồng Sơn',
      ),
      70 =>
      array(
        'maphuongxa' => 10145071,
        'tenphuongxa' => 'Xã Phúc Sơn',
      ),
      71 =>
      array(
        'maphuongxa' => 10145072,
        'tenphuongxa' => 'Xã Hương Sơn',
      ),
      72 =>
      array(
        'maphuongxa' => 10153073,
        'tenphuongxa' => 'Phường Chương Mỹ',
      ),
      73 =>
      array(
        'maphuongxa' => 10153074,
        'tenphuongxa' => 'Xã Phú Nghĩa',
      ),
      74 =>
      array(
        'maphuongxa' => 10153075,
        'tenphuongxa' => 'Xã Xuân Mai',
      ),
      75 =>
      array(
        'maphuongxa' => 10153076,
        'tenphuongxa' => 'Xã Trần Phú',
      ),
      76 =>
      array(
        'maphuongxa' => 10153077,
        'tenphuongxa' => 'Xã Hoà Phú',
      ),
      77 =>
      array(
        'maphuongxa' => 10153078,
        'tenphuongxa' => 'Xã Quảng Bị',
      ),
      78 =>
      array(
        'maphuongxa' => 10151079,
        'tenphuongxa' => 'Xã Minh Châu',
      ),
      79 =>
      array(
        'maphuongxa' => 10151080,
        'tenphuongxa' => 'Xã Quảng Oai',
      ),
      80 =>
      array(
        'maphuongxa' => 10151081,
        'tenphuongxa' => 'Xã Vật Lại',
      ),
      81 =>
      array(
        'maphuongxa' => 10151082,
        'tenphuongxa' => 'Xã Cổ Đô',
      ),
      82 =>
      array(
        'maphuongxa' => 10151083,
        'tenphuongxa' => 'Xã Bất Bạt',
      ),
      83 =>
      array(
        'maphuongxa' => 10151084,
        'tenphuongxa' => 'Xã Suối Hai',
      ),
      84 =>
      array(
        'maphuongxa' => 10151085,
        'tenphuongxa' => 'Xã Ba Vì',
      ),
      85 =>
      array(
        'maphuongxa' => 10151086,
        'tenphuongxa' => 'Xã Yên Bài',
      ),
      86 =>
      array(
        'maphuongxa' => 10129087,
        'tenphuongxa' => 'Phường Sơn Tây',
      ),
      87 =>
      array(
        'maphuongxa' => 10129088,
        'tenphuongxa' => 'Phường Tùng Thiện',
      ),
      88 =>
      array(
        'maphuongxa' => 10129089,
        'tenphuongxa' => 'Xã Đoài Phương',
      ),
      89 =>
      array(
        'maphuongxa' => 10131090,
        'tenphuongxa' => 'Xã Phúc Thọ',
      ),
      90 =>
      array(
        'maphuongxa' => 10131091,
        'tenphuongxa' => 'Xã Phúc Lộc',
      ),
      91 =>
      array(
        'maphuongxa' => 10131092,
        'tenphuongxa' => 'Xã Hát Môn',
      ),
      92 =>
      array(
        'maphuongxa' => 10135093,
        'tenphuongxa' => 'Xã Thạch Thất',
      ),
      93 =>
      array(
        'maphuongxa' => 10135094,
        'tenphuongxa' => 'Xã Hạ Bằng',
      ),
      94 =>
      array(
        'maphuongxa' => 10135095,
        'tenphuongxa' => 'Xã Tây Phương',
      ),
      95 =>
      array(
        'maphuongxa' => 10135096,
        'tenphuongxa' => 'Xã Hoà Lạc',
      ),
      96 =>
      array(
        'maphuongxa' => 10135097,
        'tenphuongxa' => 'Xã Yên Xuân',
      ),
      97 =>
      array(
        'maphuongxa' => 10139098,
        'tenphuongxa' => 'Xã Quốc Oai',
      ),
      98 =>
      array(
        'maphuongxa' => 10139099,
        'tenphuongxa' => 'Xã Hưng Đạo',
      ),
      99 =>
      array(
        'maphuongxa' => 10139100,
        'tenphuongxa' => 'Xã Kiều Phú',
      ),
      100 =>
      array(
        'maphuongxa' => 10139101,
        'tenphuongxa' => 'Xã Phú Cát',
      ),
      101 =>
      array(
        'maphuongxa' => 10137102,
        'tenphuongxa' => 'Xã Hoài Đức',
      ),
      102 =>
      array(
        'maphuongxa' => 10137103,
        'tenphuongxa' => 'Xã Dương Hoà',
      ),
      103 =>
      array(
        'maphuongxa' => 10137104,
        'tenphuongxa' => 'Xã Sơn Đồng',
      ),
      104 =>
      array(
        'maphuongxa' => 10137105,
        'tenphuongxa' => 'Xã An Khánh',
      ),
      105 =>
      array(
        'maphuongxa' => 10133106,
        'tenphuongxa' => 'Xã Đan Phượng',
      ),
      106 =>
      array(
        'maphuongxa' => 10133107,
        'tenphuongxa' => 'Xã Ô Diên',
      ),
      107 =>
      array(
        'maphuongxa' => 10133108,
        'tenphuongxa' => 'Xã Liên Minh',
      ),
      108 =>
      array(
        'maphuongxa' => 10119109,
        'tenphuongxa' => 'Xã Gia Lâm',
      ),
      109 =>
      array(
        'maphuongxa' => 10119110,
        'tenphuongxa' => 'Xã Thuận An',
      ),
      110 =>
      array(
        'maphuongxa' => 10119111,
        'tenphuongxa' => 'Xã Bát Tràng',
      ),
      111 =>
      array(
        'maphuongxa' => 10119112,
        'tenphuongxa' => 'Xã Phù Đổng',
      ),
      112 =>
      array(
        'maphuongxa' => 10117113,
        'tenphuongxa' => 'Xã Thư Lâm',
      ),
      113 =>
      array(
        'maphuongxa' => 10117114,
        'tenphuongxa' => 'Xã Đông Anh',
      ),
      114 =>
      array(
        'maphuongxa' => 10117115,
        'tenphuongxa' => 'Xã Phúc Thịnh',
      ),
      115 =>
      array(
        'maphuongxa' => 10117116,
        'tenphuongxa' => 'Xã Thiên Lộc',
      ),
      116 =>
      array(
        'maphuongxa' => 10117117,
        'tenphuongxa' => 'Xã Vĩnh Thanh',
      ),
      117 =>
      array(
        'maphuongxa' => 10125118,
        'tenphuongxa' => 'Xã Mê Linh',
      ),
      118 =>
      array(
        'maphuongxa' => 10125119,
        'tenphuongxa' => 'Xã Yên Lãng',
      ),
      119 =>
      array(
        'maphuongxa' => 10125120,
        'tenphuongxa' => 'Xã Tiến Thắng',
      ),
      120 =>
      array(
        'maphuongxa' => 10125121,
        'tenphuongxa' => 'Xã Quang Minh',
      ),
      121 =>
      array(
        'maphuongxa' => 10115122,
        'tenphuongxa' => 'Xã Sóc Sơn',
      ),
      122 =>
      array(
        'maphuongxa' => 10115123,
        'tenphuongxa' => 'Xã Đa Phúc',
      ),
      123 =>
      array(
        'maphuongxa' => 10115124,
        'tenphuongxa' => 'Xã Nội Bài',
      ),
      124 =>
      array(
        'maphuongxa' => 10115125,
        'tenphuongxa' => 'Xã Trung Giã',
      ),
      125 =>
      array(
        'maphuongxa' => 10115126,
        'tenphuongxa' => 'Xã Kim Anh',
      ),
    ),
  ),
  1 =>
  array(
    'matinhBNV' => '02',
    'matinhTMS' => '223',
    'tentinhmoi' => 'Tỉnh Bắc Ninh',
    'phuongxa' =>
    array(
      0 =>
      array(
        'maphuongxa' => 22113001,
        'tenphuongxa' => 'Xã Đại Sơn',
      ),
      1 =>
      array(
        'maphuongxa' => 22113002,
        'tenphuongxa' => 'Xã Sơn Động',
      ),
      2 =>
      array(
        'maphuongxa' => 22113003,
        'tenphuongxa' => 'Xã Tây Yên Tử',
      ),
      3 =>
      array(
        'maphuongxa' => 22113004,
        'tenphuongxa' => 'Xã Dương Hưu',
      ),
      4 =>
      array(
        'maphuongxa' => 22113005,
        'tenphuongxa' => 'Xã Yên Định',
      ),
      5 =>
      array(
        'maphuongxa' => 22113006,
        'tenphuongxa' => 'Xã An Lạc',
      ),
      6 =>
      array(
        'maphuongxa' => 22113007,
        'tenphuongxa' => 'Xã Vân Sơn',
      ),
      7 =>
      array(
        'maphuongxa' => 22107008,
        'tenphuongxa' => 'Xã Biển Động',
      ),
      8 =>
      array(
        'maphuongxa' => 22107009,
        'tenphuongxa' => 'Xã Lục Ngạn',
      ),
      9 =>
      array(
        'maphuongxa' => 22107010,
        'tenphuongxa' => 'Xã Đèo Gia',
      ),
      10 =>
      array(
        'maphuongxa' => 22107011,
        'tenphuongxa' => 'Xã Sơn Hải',
      ),
      11 =>
      array(
        'maphuongxa' => 22107012,
        'tenphuongxa' => 'Xã Tân Sơn',
      ),
      12 =>
      array(
        'maphuongxa' => 22107013,
        'tenphuongxa' => 'Xã Biên Sơn',
      ),
      13 =>
      array(
        'maphuongxa' => 22107014,
        'tenphuongxa' => 'Xã Sa Lý',
      ),
      14 =>
      array(
        'maphuongxa' => 22107015,
        'tenphuongxa' => 'Xã Nam Dương',
      ),
      15 =>
      array(
        'maphuongxa' => 22121016,
        'tenphuongxa' => 'Xã Kiên Lao',
      ),
      16 =>
      array(
        'maphuongxa' => 22121017,
        'tenphuongxa' => 'Phường Chũ',
      ),
      17 =>
      array(
        'maphuongxa' => 22121018,
        'tenphuongxa' => 'Phường Phượng Sơn',
      ),
      18 =>
      array(
        'maphuongxa' => 22115019,
        'tenphuongxa' => 'Xã Lục Sơn',
      ),
      19 =>
      array(
        'maphuongxa' => 22115020,
        'tenphuongxa' => 'Xã Trường Sơn',
      ),
      20 =>
      array(
        'maphuongxa' => 22115021,
        'tenphuongxa' => 'Xã Cẩm Lý',
      ),
      21 =>
      array(
        'maphuongxa' => 22115022,
        'tenphuongxa' => 'Xã Đông Phú',
      ),
      22 =>
      array(
        'maphuongxa' => 22115023,
        'tenphuongxa' => 'Xã Nghĩa Phương',
      ),
      23 =>
      array(
        'maphuongxa' => 22115024,
        'tenphuongxa' => 'Xã Lục Nam',
      ),
      24 =>
      array(
        'maphuongxa' => 22115025,
        'tenphuongxa' => 'Xã Bắc Lũng',
      ),
      25 =>
      array(
        'maphuongxa' => 22115026,
        'tenphuongxa' => 'Xã Bảo Đài',
      ),
      26 =>
      array(
        'maphuongxa' => 22111027,
        'tenphuongxa' => 'Xã Lạng Giang',
      ),
      27 =>
      array(
        'maphuongxa' => 22111028,
        'tenphuongxa' => 'Xã Mỹ Thái',
      ),
      28 =>
      array(
        'maphuongxa' => 22111029,
        'tenphuongxa' => 'Xã Kép',
      ),
      29 =>
      array(
        'maphuongxa' => 22111030,
        'tenphuongxa' => 'Xã Tân Dĩnh',
      ),
      30 =>
      array(
        'maphuongxa' => 22111031,
        'tenphuongxa' => 'Xã Tiên Lục',
      ),
      31 =>
      array(
        'maphuongxa' => 22103032,
        'tenphuongxa' => 'Xã Yên Thế',
      ),
      32 =>
      array(
        'maphuongxa' => 22103033,
        'tenphuongxa' => 'Xã Bố Hạ',
      ),
      33 =>
      array(
        'maphuongxa' => 22103034,
        'tenphuongxa' => 'Xã Đồng Kỳ',
      ),
      34 =>
      array(
        'maphuongxa' => 22103035,
        'tenphuongxa' => 'Xã Xuân Lương',
      ),
      35 =>
      array(
        'maphuongxa' => 22103036,
        'tenphuongxa' => 'Xã Tam Tiến',
      ),
      36 =>
      array(
        'maphuongxa' => 22105037,
        'tenphuongxa' => 'Xã Tân Yên',
      ),
      37 =>
      array(
        'maphuongxa' => 22105038,
        'tenphuongxa' => 'Xã Ngọc Thiện',
      ),
      38 =>
      array(
        'maphuongxa' => 22105039,
        'tenphuongxa' => 'Xã Nhã Nam',
      ),
      39 =>
      array(
        'maphuongxa' => 22105040,
        'tenphuongxa' => 'Xã Phúc Hoà',
      ),
      40 =>
      array(
        'maphuongxa' => 22105041,
        'tenphuongxa' => 'Xã Quang Trung',
      ),
      41 =>
      array(
        'maphuongxa' => 22109042,
        'tenphuongxa' => 'Xã Hợp Thịnh',
      ),
      42 =>
      array(
        'maphuongxa' => 22109043,
        'tenphuongxa' => 'Xã Hiệp Hoà',
      ),
      43 =>
      array(
        'maphuongxa' => 22109044,
        'tenphuongxa' => 'Xã Hoàng Vân',
      ),
      44 =>
      array(
        'maphuongxa' => 22109045,
        'tenphuongxa' => 'Xã Xuân Cẩm',
      ),
      45 =>
      array(
        'maphuongxa' => 22117046,
        'tenphuongxa' => 'Phường Tự Lạn',
      ),
      46 =>
      array(
        'maphuongxa' => 22117047,
        'tenphuongxa' => 'Phường Việt Yên',
      ),
      47 =>
      array(
        'maphuongxa' => 22117048,
        'tenphuongxa' => 'Phường Nếnh',
      ),
      48 =>
      array(
        'maphuongxa' => 22117049,
        'tenphuongxa' => 'Phường Vân Hà',
      ),
      49 =>
      array(
        'maphuongxa' => 22101050,
        'tenphuongxa' => 'Xã Đồng Việt',
      ),
      50 =>
      array(
        'maphuongxa' => 22101051,
        'tenphuongxa' => 'Phường Bắc Giang',
      ),
      51 =>
      array(
        'maphuongxa' => 22101052,
        'tenphuongxa' => 'Phường Đa Mai',
      ),
      52 =>
      array(
        'maphuongxa' => 22101053,
        'tenphuongxa' => 'Phường Tiền Phong',
      ),
      53 =>
      array(
        'maphuongxa' => 22101054,
        'tenphuongxa' => 'Phường Tân An',
      ),
      54 =>
      array(
        'maphuongxa' => 22101055,
        'tenphuongxa' => 'Phường Yên Dũng',
      ),
      55 =>
      array(
        'maphuongxa' => 22101056,
        'tenphuongxa' => 'Phường Tân Tiến',
      ),
      56 =>
      array(
        'maphuongxa' => 22101057,
        'tenphuongxa' => 'Phường Cảnh Thụy',
      ),
      57 =>
      array(
        'maphuongxa' => 22301058,
        'tenphuongxa' => 'Phường Kinh Bắc',
      ),
      58 =>
      array(
        'maphuongxa' => 22301059,
        'tenphuongxa' => 'Phường Võ Cường',
      ),
      59 =>
      array(
        'maphuongxa' => 22301060,
        'tenphuongxa' => 'Phường Vũ Ninh',
      ),
      60 =>
      array(
        'maphuongxa' => 22301061,
        'tenphuongxa' => 'Phường Hạp Lĩnh',
      ),
      61 =>
      array(
        'maphuongxa' => 22301062,
        'tenphuongxa' => 'Phường Nam Sơn',
      ),
      62 =>
      array(
        'maphuongxa' => 22313063,
        'tenphuongxa' => 'Phường Từ Sơn',
      ),
      63 =>
      array(
        'maphuongxa' => 22313064,
        'tenphuongxa' => 'Phường Tam Sơn',
      ),
      64 =>
      array(
        'maphuongxa' => 22313065,
        'tenphuongxa' => 'Phường Đồng Nguyên',
      ),
      65 =>
      array(
        'maphuongxa' => 22313066,
        'tenphuongxa' => 'Phường Phù Khê',
      ),
      66 =>
      array(
        'maphuongxa' => 22309067,
        'tenphuongxa' => 'Phường Thuận Thành',
      ),
      67 =>
      array(
        'maphuongxa' => 22309068,
        'tenphuongxa' => 'Phường Mão Điền',
      ),
      68 =>
      array(
        'maphuongxa' => 22309069,
        'tenphuongxa' => 'Phường Trạm Lộ',
      ),
      69 =>
      array(
        'maphuongxa' => 22309070,
        'tenphuongxa' => 'Phường Trí Quả',
      ),
      70 =>
      array(
        'maphuongxa' => 22309071,
        'tenphuongxa' => 'Phường Song Liễu',
      ),
      71 =>
      array(
        'maphuongxa' => 22309072,
        'tenphuongxa' => 'Phường Ninh Xá',
      ),
      72 =>
      array(
        'maphuongxa' => 22305073,
        'tenphuongxa' => 'Phường Quế Võ',
      ),
      73 =>
      array(
        'maphuongxa' => 22305074,
        'tenphuongxa' => 'Phường Phương Liễu',
      ),
      74 =>
      array(
        'maphuongxa' => 22305075,
        'tenphuongxa' => 'Phường Nhân Hoà',
      ),
      75 =>
      array(
        'maphuongxa' => 22305076,
        'tenphuongxa' => 'Phường Đào Viên',
      ),
      76 =>
      array(
        'maphuongxa' => 22305077,
        'tenphuongxa' => 'Phường Bồng Lai',
      ),
      77 =>
      array(
        'maphuongxa' => 22305078,
        'tenphuongxa' => 'Xã Chi Lăng',
      ),
      78 =>
      array(
        'maphuongxa' => 22305079,
        'tenphuongxa' => 'Xã Phù Lãng',
      ),
      79 =>
      array(
        'maphuongxa' => 22303080,
        'tenphuongxa' => 'Xã Yên Phong',
      ),
      80 =>
      array(
        'maphuongxa' => 22303081,
        'tenphuongxa' => 'Xã Văn Môn',
      ),
      81 =>
      array(
        'maphuongxa' => 22303082,
        'tenphuongxa' => 'Xã Tam Giang',
      ),
      82 =>
      array(
        'maphuongxa' => 22303083,
        'tenphuongxa' => 'Xã Yên Trung',
      ),
      83 =>
      array(
        'maphuongxa' => 22303084,
        'tenphuongxa' => 'Xã Tam Đa',
      ),
      84 =>
      array(
        'maphuongxa' => 22307085,
        'tenphuongxa' => 'Xã Tiên Du',
      ),
      85 =>
      array(
        'maphuongxa' => 22307086,
        'tenphuongxa' => 'Xã Liên Bão',
      ),
      86 =>
      array(
        'maphuongxa' => 22307087,
        'tenphuongxa' => 'Xã Tân Chi',
      ),
      87 =>
      array(
        'maphuongxa' => 22307088,
        'tenphuongxa' => 'Xã Đại Đồng',
      ),
      88 =>
      array(
        'maphuongxa' => 22307089,
        'tenphuongxa' => 'Xã Phật Tích',
      ),
      89 =>
      array(
        'maphuongxa' => 22315090,
        'tenphuongxa' => 'Xã Gia Bình',
      ),
      90 =>
      array(
        'maphuongxa' => 22315091,
        'tenphuongxa' => 'Xã Nhân Thắng',
      ),
      91 =>
      array(
        'maphuongxa' => 22315092,
        'tenphuongxa' => 'Xã Đại Lai',
      ),
      92 =>
      array(
        'maphuongxa' => 22315093,
        'tenphuongxa' => 'Xã Cao Đức',
      ),
      93 =>
      array(
        'maphuongxa' => 22315094,
        'tenphuongxa' => 'Xã Đông Cứu',
      ),
      94 =>
      array(
        'maphuongxa' => 22311095,
        'tenphuongxa' => 'Xã Lương Tài',
      ),
      95 =>
      array(
        'maphuongxa' => 22311096,
        'tenphuongxa' => 'Xã Lâm Thao',
      ),
      96 =>
      array(
        'maphuongxa' => 22311097,
        'tenphuongxa' => 'Xã Trung Chính',
      ),
      97 =>
      array(
        'maphuongxa' => 22311098,
        'tenphuongxa' => 'Xã Trung Kênh',
      ),
      98 =>
      array(
        'maphuongxa' => 22113099,
        'tenphuongxa' => 'Xã Tuấn Đạo',
      ),
    ),
  ),
  2 =>
  array(
    'matinhBNV' => '03',
    'matinhTMS' => '225',
    'tentinhmoi' => 'Tỉnh Quảng Ninh',
    'phuongxa' =>
    array(
      0 =>
      array(
        'maphuongxa' => 22521001,
        'tenphuongxa' => 'Phường An Sinh',
      ),
      1 =>
      array(
        'maphuongxa' => 22521002,
        'tenphuongxa' => 'Phường Đông Triều',
      ),
      2 =>
      array(
        'maphuongxa' => 22521003,
        'tenphuongxa' => 'Phường Bình Khê',
      ),
      3 =>
      array(
        'maphuongxa' => 22521004,
        'tenphuongxa' => 'Phường Mạo Khê',
      ),
      4 =>
      array(
        'maphuongxa' => 22521005,
        'tenphuongxa' => 'Phường Hoàng Quế',
      ),
      5 =>
      array(
        'maphuongxa' => 22505006,
        'tenphuongxa' => 'Phường Yên Tử',
      ),
      6 =>
      array(
        'maphuongxa' => 22505007,
        'tenphuongxa' => 'Phường Vàng Danh',
      ),
      7 =>
      array(
        'maphuongxa' => 22505008,
        'tenphuongxa' => 'Phường Uông Bí',
      ),
      8 =>
      array(
        'maphuongxa' => 22525009,
        'tenphuongxa' => 'Phường Đông Mai',
      ),
      9 =>
      array(
        'maphuongxa' => 22525010,
        'tenphuongxa' => 'Phường Hiệp Hoà',
      ),
      10 =>
      array(
        'maphuongxa' => 22525011,
        'tenphuongxa' => 'Phường Quảng Yên',
      ),
      11 =>
      array(
        'maphuongxa' => 22525012,
        'tenphuongxa' => 'Phường Hà An',
      ),
      12 =>
      array(
        'maphuongxa' => 22525013,
        'tenphuongxa' => 'Phường Phong Cốc',
      ),
      13 =>
      array(
        'maphuongxa' => 22525014,
        'tenphuongxa' => 'Phường Liên Hoà',
      ),
      14 =>
      array(
        'maphuongxa' => 22501015,
        'tenphuongxa' => 'Phường Tuần Châu',
      ),
      15 =>
      array(
        'maphuongxa' => 22501016,
        'tenphuongxa' => 'Phường Việt Hưng',
      ),
      16 =>
      array(
        'maphuongxa' => 22501017,
        'tenphuongxa' => 'Phường Bãi Cháy',
      ),
      17 =>
      array(
        'maphuongxa' => 22501018,
        'tenphuongxa' => 'Phường Hà Tu',
      ),
      18 =>
      array(
        'maphuongxa' => 22501019,
        'tenphuongxa' => 'Phường Hà Lầm',
      ),
      19 =>
      array(
        'maphuongxa' => 22501020,
        'tenphuongxa' => 'Phường Cao Xanh',
      ),
      20 =>
      array(
        'maphuongxa' => 22501021,
        'tenphuongxa' => 'Phường Hồng Gai',
      ),
      21 =>
      array(
        'maphuongxa' => 22501022,
        'tenphuongxa' => 'Phường Hạ Long',
      ),
      22 =>
      array(
        'maphuongxa' => 22501023,
        'tenphuongxa' => 'Phường Hoành Bồ',
      ),
      23 =>
      array(
        'maphuongxa' => 22501024,
        'tenphuongxa' => 'Xã Quảng La',
      ),
      24 =>
      array(
        'maphuongxa' => 22501025,
        'tenphuongxa' => 'Xã Thống Nhất',
      ),
      25 =>
      array(
        'maphuongxa' => 22503026,
        'tenphuongxa' => 'Phường Mông Dương',
      ),
      26 =>
      array(
        'maphuongxa' => 22503027,
        'tenphuongxa' => 'Phường Quang Hanh',
      ),
      27 =>
      array(
        'maphuongxa' => 22503028,
        'tenphuongxa' => 'Phường Cẩm Phả',
      ),
      28 =>
      array(
        'maphuongxa' => 22503029,
        'tenphuongxa' => 'Phường Cửa Ông',
      ),
      29 =>
      array(
        'maphuongxa' => 22503030,
        'tenphuongxa' => 'Xã Hải Hoà',
      ),
      30 =>
      array(
        'maphuongxa' => 22513031,
        'tenphuongxa' => 'Xã Tiên Yên',
      ),
      31 =>
      array(
        'maphuongxa' => 22513032,
        'tenphuongxa' => 'Xã Điền Xá',
      ),
      32 =>
      array(
        'maphuongxa' => 22513033,
        'tenphuongxa' => 'Xã Đông Ngũ',
      ),
      33 =>
      array(
        'maphuongxa' => 22513034,
        'tenphuongxa' => 'Xã Hải Lạng',
      ),
      34 =>
      array(
        'maphuongxa' => 22501035,
        'tenphuongxa' => 'Xã Lương Minh',
      ),
      35 =>
      array(
        'maphuongxa' => 22515036,
        'tenphuongxa' => 'Xã Kỳ Thượng',
      ),
      36 =>
      array(
        'maphuongxa' => 22515037,
        'tenphuongxa' => 'Xã Ba Chẽ',
      ),
      37 =>
      array(
        'maphuongxa' => 22527038,
        'tenphuongxa' => 'Xã Quảng Tân',
      ),
      38 =>
      array(
        'maphuongxa' => 22527039,
        'tenphuongxa' => 'Xã Đầm Hà',
      ),
      39 =>
      array(
        'maphuongxa' => 22511040,
        'tenphuongxa' => 'Xã Quảng Hà',
      ),
      40 =>
      array(
        'maphuongxa' => 22511041,
        'tenphuongxa' => 'Xã Đường Hoa',
      ),
      41 =>
      array(
        'maphuongxa' => 22511042,
        'tenphuongxa' => 'Xã Quảng Đức',
      ),
      42 =>
      array(
        'maphuongxa' => 22507043,
        'tenphuongxa' => 'Xã Hoành Mô',
      ),
      43 =>
      array(
        'maphuongxa' => 22507044,
        'tenphuongxa' => 'Xã Lục Hồn',
      ),
      44 =>
      array(
        'maphuongxa' => 22507045,
        'tenphuongxa' => 'Xã Bình Liêu',
      ),
      45 =>
      array(
        'maphuongxa' => 22509046,
        'tenphuongxa' => 'Xã Hải Sơn',
      ),
      46 =>
      array(
        'maphuongxa' => 22509047,
        'tenphuongxa' => 'Xã Hải Ninh',
      ),
      47 =>
      array(
        'maphuongxa' => 22509048,
        'tenphuongxa' => 'Xã Vĩnh Thực',
      ),
      48 =>
      array(
        'maphuongxa' => 22509049,
        'tenphuongxa' => 'Phường Móng Cái 1',
      ),
      49 =>
      array(
        'maphuongxa' => 22509050,
        'tenphuongxa' => 'Phường Móng Cái 2',
      ),
      50 =>
      array(
        'maphuongxa' => 22509051,
        'tenphuongxa' => 'Phường Móng Cái 3',
      ),
      51 =>
      array(
        'maphuongxa' => 22517052,
        'tenphuongxa' => 'Đặc khu Vân Đồn',
      ),
      52 =>
      array(
        'maphuongxa' => 22523053,
        'tenphuongxa' => 'Đặc khu Cô Tô',
      ),
      53 =>
      array(
        'maphuongxa' => 22511054,
        'tenphuongxa' => 'Xã Cái Chiên',
      ),
    ),
  ),
  3 =>
  array(
    'matinhBNV' => '04',
    'matinhTMS' => '103',
    'tentinhmoi' => 'Thành Phố Hải Phòng',
    'phuongxa' =>
    array(
      0 =>
      array(
        'maphuongxa' => 10311001,
        'tenphuongxa' => 'Phường Thuỷ Nguyên',
      ),
      1 =>
      array(
        'maphuongxa' => 10311002,
        'tenphuongxa' => 'Phường Thiên Hương',
      ),
      2 =>
      array(
        'maphuongxa' => 10311003,
        'tenphuongxa' => 'Phường Hoà Bình',
      ),
      3 =>
      array(
        'maphuongxa' => 10311004,
        'tenphuongxa' => 'Phường Nam Triệu',
      ),
      4 =>
      array(
        'maphuongxa' => 10311005,
        'tenphuongxa' => 'Phường Bạch Đằng',
      ),
      5 =>
      array(
        'maphuongxa' => 10311006,
        'tenphuongxa' => 'Phường Lưu Kiếm',
      ),
      6 =>
      array(
        'maphuongxa' => 10311007,
        'tenphuongxa' => 'Phường Lê Ích Mộc',
      ),
      7 =>
      array(
        'maphuongxa' => 10301008,
        'tenphuongxa' => 'Phường Hồng Bàng',
      ),
      8 =>
      array(
        'maphuongxa' => 10301009,
        'tenphuongxa' => 'Phường Hồng An',
      ),
      9 =>
      array(
        'maphuongxa' => 10303010,
        'tenphuongxa' => 'Phường Ngô Quyền',
      ),
      10 =>
      array(
        'maphuongxa' => 10303011,
        'tenphuongxa' => 'Phường Gia Viên',
      ),
      11 =>
      array(
        'maphuongxa' => 10305012,
        'tenphuongxa' => 'Phường Lê Chân',
      ),
      12 =>
      array(
        'maphuongxa' => 10305013,
        'tenphuongxa' => 'Phường An Biên',
      ),
      13 =>
      array(
        'maphuongxa' => 10304014,
        'tenphuongxa' => 'Phường Hải An',
      ),
      14 =>
      array(
        'maphuongxa' => 10304015,
        'tenphuongxa' => 'Phường Đông Hải',
      ),
      15 =>
      array(
        'maphuongxa' => 10307016,
        'tenphuongxa' => 'Phường Kiến An',
      ),
      16 =>
      array(
        'maphuongxa' => 10307017,
        'tenphuongxa' => 'Phường Phù Liễn',
      ),
      17 =>
      array(
        'maphuongxa' => 10309018,
        'tenphuongxa' => 'Phường Nam Đồ Sơn',
      ),
      18 =>
      array(
        'maphuongxa' => 10309019,
        'tenphuongxa' => 'Phường Đồ Sơn',
      ),
      19 =>
      array(
        'maphuongxa' => 10327020,
        'tenphuongxa' => 'Phường Hưng Đạo',
      ),
      20 =>
      array(
        'maphuongxa' => 10327021,
        'tenphuongxa' => 'Phường Dương Kinh',
      ),
      21 =>
      array(
        'maphuongxa' => 10313022,
        'tenphuongxa' => 'Phường An Dương',
      ),
      22 =>
      array(
        'maphuongxa' => 10313023,
        'tenphuongxa' => 'Phường An Hải',
      ),
      23 =>
      array(
        'maphuongxa' => 10313024,
        'tenphuongxa' => 'Phường An Phong',
      ),
      24 =>
      array(
        'maphuongxa' => 10315025,
        'tenphuongxa' => 'Xã An Hưng',
      ),
      25 =>
      array(
        'maphuongxa' => 10315026,
        'tenphuongxa' => 'Xã An Khánh',
      ),
      26 =>
      array(
        'maphuongxa' => 10315027,
        'tenphuongxa' => 'Xã An Quang',
      ),
      27 =>
      array(
        'maphuongxa' => 10315028,
        'tenphuongxa' => 'Xã An Trường',
      ),
      28 =>
      array(
        'maphuongxa' => 10315029,
        'tenphuongxa' => 'Xã An Lão',
      ),
      29 =>
      array(
        'maphuongxa' => 10317030,
        'tenphuongxa' => 'Xã Kiến Thụy',
      ),
      30 =>
      array(
        'maphuongxa' => 10317031,
        'tenphuongxa' => 'Xã Kiến Minh',
      ),
      31 =>
      array(
        'maphuongxa' => 10317032,
        'tenphuongxa' => 'Xã Kiến Hải',
      ),
      32 =>
      array(
        'maphuongxa' => 10317033,
        'tenphuongxa' => 'Xã Kiến Hưng',
      ),
      33 =>
      array(
        'maphuongxa' => 10317034,
        'tenphuongxa' => 'Xã Nghi Dương',
      ),
      34 =>
      array(
        'maphuongxa' => 10319035,
        'tenphuongxa' => 'Xã Quyết Thắng',
      ),
      35 =>
      array(
        'maphuongxa' => 10319036,
        'tenphuongxa' => 'Xã Tiên Lãng',
      ),
      36 =>
      array(
        'maphuongxa' => 10319037,
        'tenphuongxa' => 'Xã Tân Minh',
      ),
      37 =>
      array(
        'maphuongxa' => 10319038,
        'tenphuongxa' => 'Xã Tiên Minh',
      ),
      38 =>
      array(
        'maphuongxa' => 10319039,
        'tenphuongxa' => 'Xã Chấn Hưng',
      ),
      39 =>
      array(
        'maphuongxa' => 10319040,
        'tenphuongxa' => 'Xã Hùng Thắng',
      ),
      40 =>
      array(
        'maphuongxa' => 10321041,
        'tenphuongxa' => 'Xã Vĩnh Bảo',
      ),
      41 =>
      array(
        'maphuongxa' => 10321042,
        'tenphuongxa' => 'Xã Nguyễn Bỉnh Khiêm',
      ),
      42 =>
      array(
        'maphuongxa' => 10321043,
        'tenphuongxa' => 'Xã Vĩnh Am',
      ),
      43 =>
      array(
        'maphuongxa' => 10321044,
        'tenphuongxa' => 'Xã Vĩnh Hải',
      ),
      44 =>
      array(
        'maphuongxa' => 10321045,
        'tenphuongxa' => 'Xã Vĩnh Hoà',
      ),
      45 =>
      array(
        'maphuongxa' => 10321046,
        'tenphuongxa' => 'Xã Vĩnh Thịnh',
      ),
      46 =>
      array(
        'maphuongxa' => 10321047,
        'tenphuongxa' => 'Xã Vĩnh Thuận',
      ),
      47 =>
      array(
        'maphuongxa' => 10311048,
        'tenphuongxa' => 'Xã Việt Khê',
      ),
      48 =>
      array(
        'maphuongxa' => 10323049,
        'tenphuongxa' => 'Đặc khu Cát Hải',
      ),
      49 =>
      array(
        'maphuongxa' => 10325050,
        'tenphuongxa' => 'Đặc khu Bạch Long Vĩ',
      ),
      50 =>
      array(
        'maphuongxa' => 10701051,
        'tenphuongxa' => 'Phường Hải Dương',
      ),
      51 =>
      array(
        'maphuongxa' => 10701052,
        'tenphuongxa' => 'Phường Lê Thanh Nghị',
      ),
      52 =>
      array(
        'maphuongxa' => 10701053,
        'tenphuongxa' => 'Phường Việt Hoà',
      ),
      53 =>
      array(
        'maphuongxa' => 10701054,
        'tenphuongxa' => 'Phường Thành Đông',
      ),
      54 =>
      array(
        'maphuongxa' => 10701055,
        'tenphuongxa' => 'Phường Nam Đồng',
      ),
      55 =>
      array(
        'maphuongxa' => 10701056,
        'tenphuongxa' => 'Phường Tân Hưng',
      ),
      56 =>
      array(
        'maphuongxa' => 10701057,
        'tenphuongxa' => 'Phường Thạch Khôi',
      ),
      57 =>
      array(
        'maphuongxa' => 10717058,
        'tenphuongxa' => 'Phường Tứ Minh',
      ),
      58 =>
      array(
        'maphuongxa' => 10701059,
        'tenphuongxa' => 'Phường Ái Quốc',
      ),
      59 =>
      array(
        'maphuongxa' => 10703060,
        'tenphuongxa' => 'Phường Chu Văn An',
      ),
      60 =>
      array(
        'maphuongxa' => 10703061,
        'tenphuongxa' => 'Phường Chí Linh',
      ),
      61 =>
      array(
        'maphuongxa' => 10703062,
        'tenphuongxa' => 'Phường Trần Hưng Đạo',
      ),
      62 =>
      array(
        'maphuongxa' => 10703063,
        'tenphuongxa' => 'Phường Nguyễn Trãi',
      ),
      63 =>
      array(
        'maphuongxa' => 10703064,
        'tenphuongxa' => 'Phường Trần Nhân Tông',
      ),
      64 =>
      array(
        'maphuongxa' => 10703065,
        'tenphuongxa' => 'Phường Lê Đại Hành',
      ),
      65 =>
      array(
        'maphuongxa' => 10709066,
        'tenphuongxa' => 'Phường Kinh Môn',
      ),
      66 =>
      array(
        'maphuongxa' => 10709067,
        'tenphuongxa' => 'Phường Nguyễn Đại Năng',
      ),
      67 =>
      array(
        'maphuongxa' => 10709068,
        'tenphuongxa' => 'Phường Trần Liễu',
      ),
      68 =>
      array(
        'maphuongxa' => 10709069,
        'tenphuongxa' => 'Phường Bắc An Phụ',
      ),
      69 =>
      array(
        'maphuongxa' => 10709070,
        'tenphuongxa' => 'Phường Phạm Sư Mạnh',
      ),
      70 =>
      array(
        'maphuongxa' => 10709071,
        'tenphuongxa' => 'Phường Nhị Chiểu',
      ),
      71 =>
      array(
        'maphuongxa' => 10709072,
        'tenphuongxa' => 'Xã Nam An Phụ',
      ),
      72 =>
      array(
        'maphuongxa' => 10705073,
        'tenphuongxa' => 'Xã Nam Sách',
      ),
      73 =>
      array(
        'maphuongxa' => 10705074,
        'tenphuongxa' => 'Xã Thái Tân',
      ),
      74 =>
      array(
        'maphuongxa' => 10705075,
        'tenphuongxa' => 'Xã Hợp Tiến',
      ),
      75 =>
      array(
        'maphuongxa' => 10705076,
        'tenphuongxa' => 'Xã Trần Phú',
      ),
      76 =>
      array(
        'maphuongxa' => 10705077,
        'tenphuongxa' => 'Xã An Phú',
      ),
      77 =>
      array(
        'maphuongxa' => 10707078,
        'tenphuongxa' => 'Xã Thanh Hà',
      ),
      78 =>
      array(
        'maphuongxa' => 10707079,
        'tenphuongxa' => 'Xã Hà Tây',
      ),
      79 =>
      array(
        'maphuongxa' => 10707080,
        'tenphuongxa' => 'Xã Hà Bắc',
      ),
      80 =>
      array(
        'maphuongxa' => 10707081,
        'tenphuongxa' => 'Xã Hà Nam',
      ),
      81 =>
      array(
        'maphuongxa' => 10707082,
        'tenphuongxa' => 'Xã Hà Đông',
      ),
      82 =>
      array(
        'maphuongxa' => 10717083,
        'tenphuongxa' => 'Xã Cẩm Giang',
      ),
      83 =>
      array(
        'maphuongxa' => 10717084,
        'tenphuongxa' => 'Xã Tuệ Tĩnh',
      ),
      84 =>
      array(
        'maphuongxa' => 10717085,
        'tenphuongxa' => 'Xã Mao Điền',
      ),
      85 =>
      array(
        'maphuongxa' => 10717086,
        'tenphuongxa' => 'Xã Cẩm Giàng',
      ),
      86 =>
      array(
        'maphuongxa' => 10719087,
        'tenphuongxa' => 'Xã Kẻ Sặt',
      ),
      87 =>
      array(
        'maphuongxa' => 10719088,
        'tenphuongxa' => 'Xã Bình Giang',
      ),
      88 =>
      array(
        'maphuongxa' => 10719089,
        'tenphuongxa' => 'Xã Đường An',
      ),
      89 =>
      array(
        'maphuongxa' => 10719090,
        'tenphuongxa' => 'Xã Thượng Hồng',
      ),
      90 =>
      array(
        'maphuongxa' => 10713091,
        'tenphuongxa' => 'Xã Gia Lộc',
      ),
      91 =>
      array(
        'maphuongxa' => 10713092,
        'tenphuongxa' => 'Xã Yết Kiêu',
      ),
      92 =>
      array(
        'maphuongxa' => 10713093,
        'tenphuongxa' => 'Xã Gia Phúc',
      ),
      93 =>
      array(
        'maphuongxa' => 10713094,
        'tenphuongxa' => 'Xã Trường Tân',
      ),
      94 =>
      array(
        'maphuongxa' => 10715095,
        'tenphuongxa' => 'Xã Tứ Kỳ',
      ),
      95 =>
      array(
        'maphuongxa' => 10715096,
        'tenphuongxa' => 'Xã Tân Kỳ',
      ),
      96 =>
      array(
        'maphuongxa' => 10715097,
        'tenphuongxa' => 'Xã Đại Sơn',
      ),
      97 =>
      array(
        'maphuongxa' => 10715098,
        'tenphuongxa' => 'Xã Chí Minh',
      ),
      98 =>
      array(
        'maphuongxa' => 10715099,
        'tenphuongxa' => 'Xã Lạc Phượng',
      ),
      99 =>
      array(
        'maphuongxa' => 10715100,
        'tenphuongxa' => 'Xã Nguyên Giáp',
      ),
      100 =>
      array(
        'maphuongxa' => 10723101,
        'tenphuongxa' => 'Xã Ninh Giang',
      ),
      101 =>
      array(
        'maphuongxa' => 10723102,
        'tenphuongxa' => 'Xã Vĩnh Lại',
      ),
      102 =>
      array(
        'maphuongxa' => 10723103,
        'tenphuongxa' => 'Xã Khúc Thừa Dụ',
      ),
      103 =>
      array(
        'maphuongxa' => 10723104,
        'tenphuongxa' => 'Xã Tân An',
      ),
      104 =>
      array(
        'maphuongxa' => 10723105,
        'tenphuongxa' => 'Xã Hồng Châu',
      ),
      105 =>
      array(
        'maphuongxa' => 10721106,
        'tenphuongxa' => 'Xã Thanh Miện',
      ),
      106 =>
      array(
        'maphuongxa' => 10721107,
        'tenphuongxa' => 'Xã Bắc Thanh Miện',
      ),
      107 =>
      array(
        'maphuongxa' => 10721108,
        'tenphuongxa' => 'Xã Hải Hưng',
      ),
      108 =>
      array(
        'maphuongxa' => 10721109,
        'tenphuongxa' => 'Xã Nguyễn Lương Bằng',
      ),
      109 =>
      array(
        'maphuongxa' => 10721110,
        'tenphuongxa' => 'Xã Nam Thanh Miện',
      ),
      110 =>
      array(
        'maphuongxa' => 10711111,
        'tenphuongxa' => 'Xã Phú Thái',
      ),
      111 =>
      array(
        'maphuongxa' => 10711112,
        'tenphuongxa' => 'Xã Lai Khê',
      ),
      112 =>
      array(
        'maphuongxa' => 10711113,
        'tenphuongxa' => 'Xã An Thành',
      ),
      113 =>
      array(
        'maphuongxa' => 10711114,
        'tenphuongxa' => 'Xã Kim Thành',
      ),
    ),
  ),
  4 =>
  array(
    'matinhBNV' => '05',
    'matinhTMS' => '109',
    'tentinhmoi' => 'Tỉnh Hưng Yên',
    'phuongxa' =>
    array(
      0 =>
      array(
        'maphuongxa' => 10901001,
        'tenphuongxa' => 'Phường Phố Hiến',
      ),
      1 =>
      array(
        'maphuongxa' => 10901002,
        'tenphuongxa' => 'Phường Sơn Nam',
      ),
      2 =>
      array(
        'maphuongxa' => 10901003,
        'tenphuongxa' => 'Phường Hồng Châu',
      ),
      3 =>
      array(
        'maphuongxa' => 10903004,
        'tenphuongxa' => 'Phường Mỹ Hào',
      ),
      4 =>
      array(
        'maphuongxa' => 10903005,
        'tenphuongxa' => 'Phường Đường Hào',
      ),
      5 =>
      array(
        'maphuongxa' => 10903006,
        'tenphuongxa' => 'Phường Thượng Hồng',
      ),
      6 =>
      array(
        'maphuongxa' => 10901007,
        'tenphuongxa' => 'Xã Tân Hưng',
      ),
      7 =>
      array(
        'maphuongxa' => 10913008,
        'tenphuongxa' => 'Xã Hoàng Hoa Thám',
      ),
      8 =>
      array(
        'maphuongxa' => 10913009,
        'tenphuongxa' => 'Xã Tiên Lữ',
      ),
      9 =>
      array(
        'maphuongxa' => 10913010,
        'tenphuongxa' => 'Xã Tiên Hoa',
      ),
      10 =>
      array(
        'maphuongxa' => 10911011,
        'tenphuongxa' => 'Xã Quang Hưng',
      ),
      11 =>
      array(
        'maphuongxa' => 10911012,
        'tenphuongxa' => 'Xã Đoàn Đào',
      ),
      12 =>
      array(
        'maphuongxa' => 10911013,
        'tenphuongxa' => 'Xã Tiên Tiến',
      ),
      13 =>
      array(
        'maphuongxa' => 10911014,
        'tenphuongxa' => 'Xã Tống Trân',
      ),
      14 =>
      array(
        'maphuongxa' => 10909015,
        'tenphuongxa' => 'Xã Lương Bằng',
      ),
      15 =>
      array(
        'maphuongxa' => 10909016,
        'tenphuongxa' => 'Xã Nghĩa Dân',
      ),
      16 =>
      array(
        'maphuongxa' => 10909017,
        'tenphuongxa' => 'Xã Hiệp Cường',
      ),
      17 =>
      array(
        'maphuongxa' => 10909018,
        'tenphuongxa' => 'Xã Đức Hợp',
      ),
      18 =>
      array(
        'maphuongxa' => 10907019,
        'tenphuongxa' => 'Xã Ân Thi',
      ),
      19 =>
      array(
        'maphuongxa' => 10907020,
        'tenphuongxa' => 'Xã Xuân Trúc',
      ),
      20 =>
      array(
        'maphuongxa' => 10907021,
        'tenphuongxa' => 'Xã Phạm Ngũ Lão',
      ),
      21 =>
      array(
        'maphuongxa' => 10907022,
        'tenphuongxa' => 'Xã Nguyễn Trãi',
      ),
      22 =>
      array(
        'maphuongxa' => 10907023,
        'tenphuongxa' => 'Xã Hồng Quang',
      ),
      23 =>
      array(
        'maphuongxa' => 10905024,
        'tenphuongxa' => 'Xã Khoái Châu',
      ),
      24 =>
      array(
        'maphuongxa' => 10905025,
        'tenphuongxa' => 'Xã Triệu Việt Vương',
      ),
      25 =>
      array(
        'maphuongxa' => 10905026,
        'tenphuongxa' => 'Xã Việt Tiến',
      ),
      26 =>
      array(
        'maphuongxa' => 10905027,
        'tenphuongxa' => 'Xã Chí Minh',
      ),
      27 =>
      array(
        'maphuongxa' => 10905028,
        'tenphuongxa' => 'Xã Châu Ninh',
      ),
      28 =>
      array(
        'maphuongxa' => 10919029,
        'tenphuongxa' => 'Xã Yên Mỹ',
      ),
      29 =>
      array(
        'maphuongxa' => 10919030,
        'tenphuongxa' => 'Xã Việt Yên',
      ),
      30 =>
      array(
        'maphuongxa' => 10919031,
        'tenphuongxa' => 'Xã Hoàn Long',
      ),
      31 =>
      array(
        'maphuongxa' => 10919032,
        'tenphuongxa' => 'Xã Nguyễn Văn Linh',
      ),
      32 =>
      array(
        'maphuongxa' => 10917033,
        'tenphuongxa' => 'Xã Như Quỳnh',
      ),
      33 =>
      array(
        'maphuongxa' => 10917034,
        'tenphuongxa' => 'Xã Lạc Đạo',
      ),
      34 =>
      array(
        'maphuongxa' => 10917035,
        'tenphuongxa' => 'Xã Đại Đồng',
      ),
      35 =>
      array(
        'maphuongxa' => 10915036,
        'tenphuongxa' => 'Xã Nghĩa Trụ',
      ),
      36 =>
      array(
        'maphuongxa' => 10915037,
        'tenphuongxa' => 'Xã Phụng Công',
      ),
      37 =>
      array(
        'maphuongxa' => 10915038,
        'tenphuongxa' => 'Xã Văn Giang',
      ),
      38 =>
      array(
        'maphuongxa' => 10915039,
        'tenphuongxa' => 'Xã Mễ Sở',
      ),
      39 =>
      array(
        'maphuongxa' => 11501040,
        'tenphuongxa' => 'Phường Thái Bình',
      ),
      40 =>
      array(
        'maphuongxa' => 11501041,
        'tenphuongxa' => 'Phường Trần Lãm',
      ),
      41 =>
      array(
        'maphuongxa' => 11501042,
        'tenphuongxa' => 'Phường Trần Hưng Đạo',
      ),
      42 =>
      array(
        'maphuongxa' => 11501043,
        'tenphuongxa' => 'Phường Trà Lý',
      ),
      43 =>
      array(
        'maphuongxa' => 11501044,
        'tenphuongxa' => 'Phường Vũ Phúc',
      ),
      44 =>
      array(
        'maphuongxa' => 11507045,
        'tenphuongxa' => 'Xã Thái Thụy',
      ),
      45 =>
      array(
        'maphuongxa' => 11507046,
        'tenphuongxa' => 'Xã Đông Thụy Anh',
      ),
      46 =>
      array(
        'maphuongxa' => 11507047,
        'tenphuongxa' => 'Xã Bắc Thụy Anh',
      ),
      47 =>
      array(
        'maphuongxa' => 11507048,
        'tenphuongxa' => 'Xã Thụy Anh',
      ),
      48 =>
      array(
        'maphuongxa' => 11507049,
        'tenphuongxa' => 'Xã Nam Thụy Anh',
      ),
      49 =>
      array(
        'maphuongxa' => 11507050,
        'tenphuongxa' => 'Xã Bắc Thái Ninh',
      ),
      50 =>
      array(
        'maphuongxa' => 11507051,
        'tenphuongxa' => 'Xã Thái Ninh',
      ),
      51 =>
      array(
        'maphuongxa' => 11507052,
        'tenphuongxa' => 'Xã Đông Thái Ninh',
      ),
      52 =>
      array(
        'maphuongxa' => 11507053,
        'tenphuongxa' => 'Xã Nam Thái Ninh',
      ),
      53 =>
      array(
        'maphuongxa' => 11507054,
        'tenphuongxa' => 'Xã Tây Thái Ninh',
      ),
      54 =>
      array(
        'maphuongxa' => 11507055,
        'tenphuongxa' => 'Xã Tây Thụy Anh',
      ),
      55 =>
      array(
        'maphuongxa' => 11515056,
        'tenphuongxa' => 'Xã Tiền Hải',
      ),
      56 =>
      array(
        'maphuongxa' => 11515057,
        'tenphuongxa' => 'Xã Tây Tiền Hải',
      ),
      57 =>
      array(
        'maphuongxa' => 11515058,
        'tenphuongxa' => 'Xã Ái Quốc',
      ),
      58 =>
      array(
        'maphuongxa' => 11515059,
        'tenphuongxa' => 'Xã Đồng Châu',
      ),
      59 =>
      array(
        'maphuongxa' => 11515060,
        'tenphuongxa' => 'Xã Đông Tiền Hải',
      ),
      60 =>
      array(
        'maphuongxa' => 11515061,
        'tenphuongxa' => 'Xã Nam Cường',
      ),
      61 =>
      array(
        'maphuongxa' => 11515062,
        'tenphuongxa' => 'Xã Hưng Phú',
      ),
      62 =>
      array(
        'maphuongxa' => 11515063,
        'tenphuongxa' => 'Xã Nam Tiền Hải',
      ),
      63 =>
      array(
        'maphuongxa' => 11503064,
        'tenphuongxa' => 'Xã Quỳnh Phụ',
      ),
      64 =>
      array(
        'maphuongxa' => 11503065,
        'tenphuongxa' => 'Xã Minh Thọ',
      ),
      65 =>
      array(
        'maphuongxa' => 11503066,
        'tenphuongxa' => 'Xã Nguyễn Du',
      ),
      66 =>
      array(
        'maphuongxa' => 11503067,
        'tenphuongxa' => 'Xã Quỳnh An',
      ),
      67 =>
      array(
        'maphuongxa' => 11503068,
        'tenphuongxa' => 'Xã Ngọc Lâm',
      ),
      68 =>
      array(
        'maphuongxa' => 11503069,
        'tenphuongxa' => 'Xã Đồng Bằng',
      ),
      69 =>
      array(
        'maphuongxa' => 11503070,
        'tenphuongxa' => 'Xã A Sào',
      ),
      70 =>
      array(
        'maphuongxa' => 11503071,
        'tenphuongxa' => 'Xã Phụ Dực',
      ),
      71 =>
      array(
        'maphuongxa' => 11503072,
        'tenphuongxa' => 'Xã Tân Tiến',
      ),
      72 =>
      array(
        'maphuongxa' => 11505073,
        'tenphuongxa' => 'Xã Hưng Hà',
      ),
      73 =>
      array(
        'maphuongxa' => 11505074,
        'tenphuongxa' => 'Xã Tiên La',
      ),
      74 =>
      array(
        'maphuongxa' => 11505075,
        'tenphuongxa' => 'Xã Lê Quý Đôn',
      ),
      75 =>
      array(
        'maphuongxa' => 11505076,
        'tenphuongxa' => 'Xã Hồng Minh',
      ),
      76 =>
      array(
        'maphuongxa' => 11505077,
        'tenphuongxa' => 'Xã Thần Khê',
      ),
      77 =>
      array(
        'maphuongxa' => 11505078,
        'tenphuongxa' => 'Xã Diên Hà',
      ),
      78 =>
      array(
        'maphuongxa' => 11505079,
        'tenphuongxa' => 'Xã Ngự Thiên',
      ),
      79 =>
      array(
        'maphuongxa' => 11505080,
        'tenphuongxa' => 'Xã Long Hưng',
      ),
      80 =>
      array(
        'maphuongxa' => 11509081,
        'tenphuongxa' => 'Xã Đông Hưng',
      ),
      81 =>
      array(
        'maphuongxa' => 11509082,
        'tenphuongxa' => 'Xã Bắc Tiên Hưng',
      ),
      82 =>
      array(
        'maphuongxa' => 11509083,
        'tenphuongxa' => 'Xã Đông Tiên Hưng',
      ),
      83 =>
      array(
        'maphuongxa' => 11509084,
        'tenphuongxa' => 'Xã Nam Đông Hưng',
      ),
      84 =>
      array(
        'maphuongxa' => 11509085,
        'tenphuongxa' => 'Xã Bắc Đông Quan',
      ),
      85 =>
      array(
        'maphuongxa' => 11509086,
        'tenphuongxa' => 'Xã Bắc Đông Hưng',
      ),
      86 =>
      array(
        'maphuongxa' => 11509087,
        'tenphuongxa' => 'Xã Đông Quan',
      ),
      87 =>
      array(
        'maphuongxa' => 11509088,
        'tenphuongxa' => 'Xã Nam Tiên Hưng',
      ),
      88 =>
      array(
        'maphuongxa' => 11509089,
        'tenphuongxa' => 'Xã Tiên Hưng',
      ),
      89 =>
      array(
        'maphuongxa' => 11513090,
        'tenphuongxa' => 'Xã Lê Lợi',
      ),
      90 =>
      array(
        'maphuongxa' => 11513091,
        'tenphuongxa' => 'Xã Kiến Xương',
      ),
      91 =>
      array(
        'maphuongxa' => 11513092,
        'tenphuongxa' => 'Xã Quang Lịch',
      ),
      92 =>
      array(
        'maphuongxa' => 11513093,
        'tenphuongxa' => 'Xã Vũ Quý',
      ),
      93 =>
      array(
        'maphuongxa' => 11513094,
        'tenphuongxa' => 'Xã Bình Thanh',
      ),
      94 =>
      array(
        'maphuongxa' => 11513095,
        'tenphuongxa' => 'Xã Bình Định',
      ),
      95 =>
      array(
        'maphuongxa' => 11513096,
        'tenphuongxa' => 'Xã Hồng Vũ',
      ),
      96 =>
      array(
        'maphuongxa' => 11513097,
        'tenphuongxa' => 'Xã Bình Nguyên',
      ),
      97 =>
      array(
        'maphuongxa' => 11513098,
        'tenphuongxa' => 'Xã Trà Giang',
      ),
      98 =>
      array(
        'maphuongxa' => 11511099,
        'tenphuongxa' => 'Xã Vũ Thư',
      ),
      99 =>
      array(
        'maphuongxa' => 11511100,
        'tenphuongxa' => 'Xã Thư Trì',
      ),
      100 =>
      array(
        'maphuongxa' => 11511101,
        'tenphuongxa' => 'Xã Tân Thuận',
      ),
      101 =>
      array(
        'maphuongxa' => 11511102,
        'tenphuongxa' => 'Xã Thư Vũ',
      ),
      102 =>
      array(
        'maphuongxa' => 11511103,
        'tenphuongxa' => 'Xã Vũ Tiên',
      ),
      103 =>
      array(
        'maphuongxa' => 11511104,
        'tenphuongxa' => 'Xã Vạn Xuân',
      ),
    ),
  ),
  5 =>
  array(
    'matinhBNV' => '06',
    'matinhTMS' => '117',
    'tentinhmoi' => 'Tỉnh Ninh Bình',
    'phuongxa' =>
    array(
      0 =>
      array(
        'maphuongxa' => 11707001,
        'tenphuongxa' => 'Xã Gia Viễn',
      ),
      1 =>
      array(
        'maphuongxa' => 11707002,
        'tenphuongxa' => 'Xã Đại Hoàng',
      ),
      2 =>
      array(
        'maphuongxa' => 11707003,
        'tenphuongxa' => 'Xã Gia Hưng',
      ),
      3 =>
      array(
        'maphuongxa' => 11707004,
        'tenphuongxa' => 'Xã Gia Phong',
      ),
      4 =>
      array(
        'maphuongxa' => 11707005,
        'tenphuongxa' => 'Xã Gia Vân',
      ),
      5 =>
      array(
        'maphuongxa' => 11707006,
        'tenphuongxa' => 'Xã Gia Trấn',
      ),
      6 =>
      array(
        'maphuongxa' => 11705007,
        'tenphuongxa' => 'Xã Nho Quan',
      ),
      7 =>
      array(
        'maphuongxa' => 11705008,
        'tenphuongxa' => 'Xã Gia Lâm',
      ),
      8 =>
      array(
        'maphuongxa' => 11705009,
        'tenphuongxa' => 'Xã Gia Tường',
      ),
      9 =>
      array(
        'maphuongxa' => 11705010,
        'tenphuongxa' => 'Xã Phú Sơn',
      ),
      10 =>
      array(
        'maphuongxa' => 11705011,
        'tenphuongxa' => 'Xã Cúc Phương',
      ),
      11 =>
      array(
        'maphuongxa' => 11705012,
        'tenphuongxa' => 'Xã Phú Long',
      ),
      12 =>
      array(
        'maphuongxa' => 11705013,
        'tenphuongxa' => 'Xã Thanh Sơn',
      ),
      13 =>
      array(
        'maphuongxa' => 11705014,
        'tenphuongxa' => 'Xã Quỳnh Lưu',
      ),
      14 =>
      array(
        'maphuongxa' => 11713015,
        'tenphuongxa' => 'Xã Yên Khánh',
      ),
      15 =>
      array(
        'maphuongxa' => 11713016,
        'tenphuongxa' => 'Xã Khánh Nhạc',
      ),
      16 =>
      array(
        'maphuongxa' => 11713017,
        'tenphuongxa' => 'Xã Khánh Thiện',
      ),
      17 =>
      array(
        'maphuongxa' => 11713018,
        'tenphuongxa' => 'Xã Khánh Hội',
      ),
      18 =>
      array(
        'maphuongxa' => 11713019,
        'tenphuongxa' => 'Xã Khánh Trung',
      ),
      19 =>
      array(
        'maphuongxa' => 11711020,
        'tenphuongxa' => 'Xã Yên Mô',
      ),
      20 =>
      array(
        'maphuongxa' => 11711021,
        'tenphuongxa' => 'Xã Yên Từ',
      ),
      21 =>
      array(
        'maphuongxa' => 11711022,
        'tenphuongxa' => 'Xã Yên Mạc',
      ),
      22 =>
      array(
        'maphuongxa' => 11711023,
        'tenphuongxa' => 'Xã Đồng Thái',
      ),
      23 =>
      array(
        'maphuongxa' => 11715024,
        'tenphuongxa' => 'Xã Chất Bình',
      ),
      24 =>
      array(
        'maphuongxa' => 11715025,
        'tenphuongxa' => 'Xã Kim Sơn',
      ),
      25 =>
      array(
        'maphuongxa' => 11715026,
        'tenphuongxa' => 'Xã Quang Thiện',
      ),
      26 =>
      array(
        'maphuongxa' => 11715027,
        'tenphuongxa' => 'Xã Phát Diệm',
      ),
      27 =>
      array(
        'maphuongxa' => 11715028,
        'tenphuongxa' => 'Xã Lai Thành',
      ),
      28 =>
      array(
        'maphuongxa' => 11715029,
        'tenphuongxa' => 'Xã Định Hóa',
      ),
      29 =>
      array(
        'maphuongxa' => 11715030,
        'tenphuongxa' => 'Xã Bình Minh',
      ),
      30 =>
      array(
        'maphuongxa' => 11715031,
        'tenphuongxa' => 'Xã Kim Đông',
      ),
      31 =>
      array(
        'maphuongxa' => 11111032,
        'tenphuongxa' => 'Xã Bình Lục',
      ),
      32 =>
      array(
        'maphuongxa' => 11111033,
        'tenphuongxa' => 'Xã Bình Mỹ',
      ),
      33 =>
      array(
        'maphuongxa' => 11111034,
        'tenphuongxa' => 'Xã Bình An',
      ),
      34 =>
      array(
        'maphuongxa' => 11111035,
        'tenphuongxa' => 'Xã Bình Giang',
      ),
      35 =>
      array(
        'maphuongxa' => 11111036,
        'tenphuongxa' => 'Xã Bình Sơn',
      ),
      36 =>
      array(
        'maphuongxa' => 11109037,
        'tenphuongxa' => 'Xã Liêm Hà',
      ),
      37 =>
      array(
        'maphuongxa' => 11109038,
        'tenphuongxa' => 'Xã Tân Thanh',
      ),
      38 =>
      array(
        'maphuongxa' => 11109039,
        'tenphuongxa' => 'Xã Thanh Bình',
      ),
      39 =>
      array(
        'maphuongxa' => 11109040,
        'tenphuongxa' => 'Xã Thanh Lâm',
      ),
      40 =>
      array(
        'maphuongxa' => 11109041,
        'tenphuongxa' => 'Xã Thanh Liêm',
      ),
      41 =>
      array(
        'maphuongxa' => 11107042,
        'tenphuongxa' => 'Xã Lý Nhân',
      ),
      42 =>
      array(
        'maphuongxa' => 11107043,
        'tenphuongxa' => 'Xã Nam Xang',
      ),
      43 =>
      array(
        'maphuongxa' => 11107044,
        'tenphuongxa' => 'Xã Bắc Lý',
      ),
      44 =>
      array(
        'maphuongxa' => 11107045,
        'tenphuongxa' => 'Xã Vĩnh Trụ',
      ),
      45 =>
      array(
        'maphuongxa' => 11107046,
        'tenphuongxa' => 'Xã Trần Thương',
      ),
      46 =>
      array(
        'maphuongxa' => 11107047,
        'tenphuongxa' => 'Xã Nhân Hà',
      ),
      47 =>
      array(
        'maphuongxa' => 11107048,
        'tenphuongxa' => 'Xã Nam Lý',
      ),
      48 =>
      array(
        'maphuongxa' => 11309049,
        'tenphuongxa' => 'Xã Nam Trực',
      ),
      49 =>
      array(
        'maphuongxa' => 11309050,
        'tenphuongxa' => 'Xã Nam Minh',
      ),
      50 =>
      array(
        'maphuongxa' => 11309051,
        'tenphuongxa' => 'Xã Nam Đồng',
      ),
      51 =>
      array(
        'maphuongxa' => 11309052,
        'tenphuongxa' => 'Xã Nam Ninh',
      ),
      52 =>
      array(
        'maphuongxa' => 11309053,
        'tenphuongxa' => 'Xã Nam Hồng',
      ),
      53 =>
      array(
        'maphuongxa' => 11303054,
        'tenphuongxa' => 'Xã Minh Tân',
      ),
      54 =>
      array(
        'maphuongxa' => 11303055,
        'tenphuongxa' => 'Xã Hiển Khánh',
      ),
      55 =>
      array(
        'maphuongxa' => 11303056,
        'tenphuongxa' => 'Xã Vụ Bản',
      ),
      56 =>
      array(
        'maphuongxa' => 11303057,
        'tenphuongxa' => 'Xã Liên Minh',
      ),
      57 =>
      array(
        'maphuongxa' => 11307058,
        'tenphuongxa' => 'Xã Ý Yên',
      ),
      58 =>
      array(
        'maphuongxa' => 11307059,
        'tenphuongxa' => 'Xã Yên Đồng',
      ),
      59 =>
      array(
        'maphuongxa' => 11307060,
        'tenphuongxa' => 'Xã Yên Cường',
      ),
      60 =>
      array(
        'maphuongxa' => 11307061,
        'tenphuongxa' => 'Xã Vạn Thắng',
      ),
      61 =>
      array(
        'maphuongxa' => 11307062,
        'tenphuongxa' => 'Xã Vũ Dương',
      ),
      62 =>
      array(
        'maphuongxa' => 11307063,
        'tenphuongxa' => 'Xã Tân Minh',
      ),
      63 =>
      array(
        'maphuongxa' => 11307064,
        'tenphuongxa' => 'Xã Phong Doanh',
      ),
      64 =>
      array(
        'maphuongxa' => 11311065,
        'tenphuongxa' => 'Xã Cổ Lễ',
      ),
      65 =>
      array(
        'maphuongxa' => 11311066,
        'tenphuongxa' => 'Xã Ninh Giang',
      ),
      66 =>
      array(
        'maphuongxa' => 11311067,
        'tenphuongxa' => 'Xã Cát Thành',
      ),
      67 =>
      array(
        'maphuongxa' => 11311068,
        'tenphuongxa' => 'Xã Trực Ninh',
      ),
      68 =>
      array(
        'maphuongxa' => 11311069,
        'tenphuongxa' => 'Xã Quang Hưng',
      ),
      69 =>
      array(
        'maphuongxa' => 11311070,
        'tenphuongxa' => 'Xã Minh Thái',
      ),
      70 =>
      array(
        'maphuongxa' => 11311071,
        'tenphuongxa' => 'Xã Ninh Cường',
      ),
      71 =>
      array(
        'maphuongxa' => 11313072,
        'tenphuongxa' => 'Xã Xuân Trường',
      ),
      72 =>
      array(
        'maphuongxa' => 11313073,
        'tenphuongxa' => 'Xã Xuân Hưng',
      ),
      73 =>
      array(
        'maphuongxa' => 11313074,
        'tenphuongxa' => 'Xã Xuân Giang',
      ),
      74 =>
      array(
        'maphuongxa' => 11313075,
        'tenphuongxa' => 'Xã Xuân Hồng',
      ),
      75 =>
      array(
        'maphuongxa' => 11319076,
        'tenphuongxa' => 'Xã Hải Hậu',
      ),
      76 =>
      array(
        'maphuongxa' => 11319077,
        'tenphuongxa' => 'Xã Hải Anh',
      ),
      77 =>
      array(
        'maphuongxa' => 11319078,
        'tenphuongxa' => 'Xã Hải Tiến',
      ),
      78 =>
      array(
        'maphuongxa' => 11319079,
        'tenphuongxa' => 'Xã Hải Hưng',
      ),
      79 =>
      array(
        'maphuongxa' => 11319080,
        'tenphuongxa' => 'Xã Hải An',
      ),
      80 =>
      array(
        'maphuongxa' => 11319081,
        'tenphuongxa' => 'Xã Hải Quang',
      ),
      81 =>
      array(
        'maphuongxa' => 11319082,
        'tenphuongxa' => 'Xã Hải Xuân',
      ),
      82 =>
      array(
        'maphuongxa' => 11319083,
        'tenphuongxa' => 'Xã Hải Thịnh',
      ),
      83 =>
      array(
        'maphuongxa' => 11315084,
        'tenphuongxa' => 'Xã Giao Minh',
      ),
      84 =>
      array(
        'maphuongxa' => 11315085,
        'tenphuongxa' => 'Xã Giao Hoà',
      ),
      85 =>
      array(
        'maphuongxa' => 11315086,
        'tenphuongxa' => 'Xã Giao Thuỷ',
      ),
      86 =>
      array(
        'maphuongxa' => 11315087,
        'tenphuongxa' => 'Xã Giao Phúc',
      ),
      87 =>
      array(
        'maphuongxa' => 11315088,
        'tenphuongxa' => 'Xã Giao Hưng',
      ),
      88 =>
      array(
        'maphuongxa' => 11315089,
        'tenphuongxa' => 'Xã Giao Bình',
      ),
      89 =>
      array(
        'maphuongxa' => 11315090,
        'tenphuongxa' => 'Xã Giao Ninh',
      ),
      90 =>
      array(
        'maphuongxa' => 11317091,
        'tenphuongxa' => 'Xã Đồng Thịnh',
      ),
      91 =>
      array(
        'maphuongxa' => 11317092,
        'tenphuongxa' => 'Xã Nghĩa Hưng',
      ),
      92 =>
      array(
        'maphuongxa' => 11317093,
        'tenphuongxa' => 'Xã Nghĩa Sơn',
      ),
      93 =>
      array(
        'maphuongxa' => 11317094,
        'tenphuongxa' => 'Xã Hồng Phong',
      ),
      94 =>
      array(
        'maphuongxa' => 11317095,
        'tenphuongxa' => 'Xã Quỹ Nhất',
      ),
      95 =>
      array(
        'maphuongxa' => 11317096,
        'tenphuongxa' => 'Xã Nghĩa Lâm',
      ),
      96 =>
      array(
        'maphuongxa' => 11317097,
        'tenphuongxa' => 'Xã Rạng Đông',
      ),
      97 =>
      array(
        'maphuongxa' => 11709098,
        'tenphuongxa' => 'Phường Tây Hoa Lư',
      ),
      98 =>
      array(
        'maphuongxa' => 11709099,
        'tenphuongxa' => 'Phường Hoa Lư',
      ),
      99 =>
      array(
        'maphuongxa' => 11709100,
        'tenphuongxa' => 'Phường Nam Hoa Lư',
      ),
      100 =>
      array(
        'maphuongxa' => 11713101,
        'tenphuongxa' => 'Phường Đông Hoa Lư',
      ),
      101 =>
      array(
        'maphuongxa' => 11703102,
        'tenphuongxa' => 'Phường Tam Điệp',
      ),
      102 =>
      array(
        'maphuongxa' => 11703103,
        'tenphuongxa' => 'Phường Yên Sơn',
      ),
      103 =>
      array(
        'maphuongxa' => 11703104,
        'tenphuongxa' => 'Phường Trung Sơn',
      ),
      104 =>
      array(
        'maphuongxa' => 11703105,
        'tenphuongxa' => 'Phường Yên Thắng',
      ),
      105 =>
      array(
        'maphuongxa' => 11101106,
        'tenphuongxa' => 'Phường Hà Nam',
      ),
      106 =>
      array(
        'maphuongxa' => 11101107,
        'tenphuongxa' => 'Phường Phủ Lý',
      ),
      107 =>
      array(
        'maphuongxa' => 11101108,
        'tenphuongxa' => 'Phường Phù Vân',
      ),
      108 =>
      array(
        'maphuongxa' => 11101109,
        'tenphuongxa' => 'Phường Châu Sơn',
      ),
      109 =>
      array(
        'maphuongxa' => 11101110,
        'tenphuongxa' => 'Phường Liêm Tuyền',
      ),
      110 =>
      array(
        'maphuongxa' => 11103111,
        'tenphuongxa' => 'Phường Duy Tiên',
      ),
      111 =>
      array(
        'maphuongxa' => 11103112,
        'tenphuongxa' => 'Phường Duy Tân',
      ),
      112 =>
      array(
        'maphuongxa' => 11103113,
        'tenphuongxa' => 'Phường Đồng Văn',
      ),
      113 =>
      array(
        'maphuongxa' => 11103114,
        'tenphuongxa' => 'Phường Duy Hà',
      ),
      114 =>
      array(
        'maphuongxa' => 11103115,
        'tenphuongxa' => 'Phường Tiên Sơn',
      ),
      115 =>
      array(
        'maphuongxa' => 11105116,
        'tenphuongxa' => 'Phường Lê Hồ',
      ),
      116 =>
      array(
        'maphuongxa' => 11105117,
        'tenphuongxa' => 'Phường Nguyễn Úy',
      ),
      117 =>
      array(
        'maphuongxa' => 11105118,
        'tenphuongxa' => 'Phường Lý Thường Kiệt',
      ),
      118 =>
      array(
        'maphuongxa' => 11105119,
        'tenphuongxa' => 'Phường Kim Thanh',
      ),
      119 =>
      array(
        'maphuongxa' => 11105120,
        'tenphuongxa' => 'Phường Tam Chúc',
      ),
      120 =>
      array(
        'maphuongxa' => 11105121,
        'tenphuongxa' => 'Phường Kim Bảng',
      ),
      121 =>
      array(
        'maphuongxa' => 11301122,
        'tenphuongxa' => 'Phường Nam Định',
      ),
      122 =>
      array(
        'maphuongxa' => 11301123,
        'tenphuongxa' => 'Phường Thiên Trường',
      ),
      123 =>
      array(
        'maphuongxa' => 11301124,
        'tenphuongxa' => 'Phường Đông A',
      ),
      124 =>
      array(
        'maphuongxa' => 11301125,
        'tenphuongxa' => 'Phường Vị Khê',
      ),
      125 =>
      array(
        'maphuongxa' => 11301126,
        'tenphuongxa' => 'Phường Thành Nam',
      ),
      126 =>
      array(
        'maphuongxa' => 11301127,
        'tenphuongxa' => 'Phường Trường Thi',
      ),
      127 =>
      array(
        'maphuongxa' => 11309128,
        'tenphuongxa' => 'Phường Hồng Quang',
      ),
      128 =>
      array(
        'maphuongxa' => 11301129,
        'tenphuongxa' => 'Phường Mỹ Lộc',
      ),
    ),
  ),
  6 =>
  array(
    'matinhBNV' => '07',
    'matinhTMS' => '203',
    'tentinhmoi' => 'Tỉnh Cao Bằng',
    'phuongxa' =>
    array(
      0 =>
      array(
        'maphuongxa' => 20301001,
        'tenphuongxa' => 'Phường Thục Phán',
      ),
      1 =>
      array(
        'maphuongxa' => 20301002,
        'tenphuongxa' => 'Phường Nùng Trí Cao',
      ),
      2 =>
      array(
        'maphuongxa' => 20301003,
        'tenphuongxa' => 'Phường Tân Giang',
      ),
      3 =>
      array(
        'maphuongxa' => 20323004,
        'tenphuongxa' => 'Xã Quảng Lâm',
      ),
      4 =>
      array(
        'maphuongxa' => 20323005,
        'tenphuongxa' => 'Xã Nam Quang',
      ),
      5 =>
      array(
        'maphuongxa' => 20323006,
        'tenphuongxa' => 'Xã Lý Bôn',
      ),
      6 =>
      array(
        'maphuongxa' => 20323007,
        'tenphuongxa' => 'Xã Bảo Lâm',
      ),
      7 =>
      array(
        'maphuongxa' => 20323008,
        'tenphuongxa' => 'Xã Yên Thổ',
      ),
      8 =>
      array(
        'maphuongxa' => 20303009,
        'tenphuongxa' => 'Xã Sơn Lộ',
      ),
      9 =>
      array(
        'maphuongxa' => 20303010,
        'tenphuongxa' => 'Xã Hưng Đạo',
      ),
      10 =>
      array(
        'maphuongxa' => 20303011,
        'tenphuongxa' => 'Xã Bảo Lạc',
      ),
      11 =>
      array(
        'maphuongxa' => 20303012,
        'tenphuongxa' => 'Xã Cốc Pàng',
      ),
      12 =>
      array(
        'maphuongxa' => 20303013,
        'tenphuongxa' => 'Xã Cô Ba',
      ),
      13 =>
      array(
        'maphuongxa' => 20303014,
        'tenphuongxa' => 'Xã Khánh Xuân',
      ),
      14 =>
      array(
        'maphuongxa' => 20303015,
        'tenphuongxa' => 'Xã Xuân Trường',
      ),
      15 =>
      array(
        'maphuongxa' => 20303016,
        'tenphuongxa' => 'Xã Huy Giáp',
      ),
      16 =>
      array(
        'maphuongxa' => 20313017,
        'tenphuongxa' => 'Xã Ca Thành',
      ),
      17 =>
      array(
        'maphuongxa' => 20313018,
        'tenphuongxa' => 'Xã Phan Thanh',
      ),
      18 =>
      array(
        'maphuongxa' => 20313019,
        'tenphuongxa' => 'Xã Thành Công',
      ),
      19 =>
      array(
        'maphuongxa' => 20313020,
        'tenphuongxa' => 'Xã Tĩnh Túc',
      ),
      20 =>
      array(
        'maphuongxa' => 20313021,
        'tenphuongxa' => 'Xã Tam Kim',
      ),
      21 =>
      array(
        'maphuongxa' => 20313022,
        'tenphuongxa' => 'Xã Nguyên Bình',
      ),
      22 =>
      array(
        'maphuongxa' => 20313023,
        'tenphuongxa' => 'Xã Minh Tâm',
      ),
      23 =>
      array(
        'maphuongxa' => 20305024,
        'tenphuongxa' => 'Xã Thanh Long',
      ),
      24 =>
      array(
        'maphuongxa' => 20305025,
        'tenphuongxa' => 'Xã Cần Yên',
      ),
      25 =>
      array(
        'maphuongxa' => 20305026,
        'tenphuongxa' => 'Xã Thông Nông',
      ),
      26 =>
      array(
        'maphuongxa' => 20305027,
        'tenphuongxa' => 'Xã Trường Hà',
      ),
      27 =>
      array(
        'maphuongxa' => 20305028,
        'tenphuongxa' => 'Xã Hà Quảng',
      ),
      28 =>
      array(
        'maphuongxa' => 20305029,
        'tenphuongxa' => 'Xã Lũng Nặm',
      ),
      29 =>
      array(
        'maphuongxa' => 20305030,
        'tenphuongxa' => 'Xã Tổng Cọt',
      ),
      30 =>
      array(
        'maphuongxa' => 20315031,
        'tenphuongxa' => 'Xã Nam Tuấn',
      ),
      31 =>
      array(
        'maphuongxa' => 20315032,
        'tenphuongxa' => 'Xã Hoà An',
      ),
      32 =>
      array(
        'maphuongxa' => 20315033,
        'tenphuongxa' => 'Xã Bạch Đằng',
      ),
      33 =>
      array(
        'maphuongxa' => 20315034,
        'tenphuongxa' => 'Xã Nguyễn Huệ',
      ),
      34 =>
      array(
        'maphuongxa' => 20321035,
        'tenphuongxa' => 'Xã Minh Khai',
      ),
      35 =>
      array(
        'maphuongxa' => 20321036,
        'tenphuongxa' => 'Xã Canh Tân',
      ),
      36 =>
      array(
        'maphuongxa' => 20321037,
        'tenphuongxa' => 'Xã Kim Đồng',
      ),
      37 =>
      array(
        'maphuongxa' => 20321038,
        'tenphuongxa' => 'Xã Thạch An',
      ),
      38 =>
      array(
        'maphuongxa' => 20321039,
        'tenphuongxa' => 'Xã Đông Khê',
      ),
      39 =>
      array(
        'maphuongxa' => 20321040,
        'tenphuongxa' => 'Xã Đức Long',
      ),
      40 =>
      array(
        'maphuongxa' => 20317041,
        'tenphuongxa' => 'Xã Phục Hoà',
      ),
      41 =>
      array(
        'maphuongxa' => 20317042,
        'tenphuongxa' => 'Xã Bế Văn Đàn',
      ),
      42 =>
      array(
        'maphuongxa' => 20317043,
        'tenphuongxa' => 'Xã Độc Lập',
      ),
      43 =>
      array(
        'maphuongxa' => 20317044,
        'tenphuongxa' => 'Xã Quảng Uyên',
      ),
      44 =>
      array(
        'maphuongxa' => 20317045,
        'tenphuongxa' => 'Xã Hạnh Phúc',
      ),
      45 =>
      array(
        'maphuongxa' => 20311046,
        'tenphuongxa' => 'Xã Quang Hán',
      ),
      46 =>
      array(
        'maphuongxa' => 20311047,
        'tenphuongxa' => 'Xã Trà Lĩnh',
      ),
      47 =>
      array(
        'maphuongxa' => 20311048,
        'tenphuongxa' => 'Xã Quang Trung',
      ),
      48 =>
      array(
        'maphuongxa' => 20311049,
        'tenphuongxa' => 'Xã Đoài Dương',
      ),
      49 =>
      array(
        'maphuongxa' => 20311050,
        'tenphuongxa' => 'Xã Trùng Khánh',
      ),
      50 =>
      array(
        'maphuongxa' => 20311051,
        'tenphuongxa' => 'Xã Đàm Thuỷ',
      ),
      51 =>
      array(
        'maphuongxa' => 20311052,
        'tenphuongxa' => 'Xã Đình Phong',
      ),
      52 =>
      array(
        'maphuongxa' => 20319053,
        'tenphuongxa' => 'Xã Lý Quốc',
      ),
      53 =>
      array(
        'maphuongxa' => 20319054,
        'tenphuongxa' => 'Xã Hạ Lang',
      ),
      54 =>
      array(
        'maphuongxa' => 20319055,
        'tenphuongxa' => 'Xã Vinh Quý',
      ),
      55 =>
      array(
        'maphuongxa' => 20319056,
        'tenphuongxa' => 'Xã Quang Long',
      ),
    ),
  ),
  7 =>
  array(
    'matinhBNV' => '08',
    'matinhTMS' => '211',
    'tentinhmoi' => 'Tỉnh Tuyên Quang',
    'phuongxa' =>
    array(
      0 =>
      array(
        'maphuongxa' => 21113001,
        'tenphuongxa' => 'Xã Thượng Lâm',
      ),
      1 =>
      array(
        'maphuongxa' => 21113002,
        'tenphuongxa' => 'Xã Lâm Bình',
      ),
      2 =>
      array(
        'maphuongxa' => 21113003,
        'tenphuongxa' => 'Xã Minh Quang',
      ),
      3 =>
      array(
        'maphuongxa' => 21113004,
        'tenphuongxa' => 'Xã Bình An',
      ),
      4 =>
      array(
        'maphuongxa' => 21103005,
        'tenphuongxa' => 'Xã Côn Lôn',
      ),
      5 =>
      array(
        'maphuongxa' => 21103006,
        'tenphuongxa' => 'Xã Yên Hoa',
      ),
      6 =>
      array(
        'maphuongxa' => 21103007,
        'tenphuongxa' => 'Xã Thượng Nông',
      ),
      7 =>
      array(
        'maphuongxa' => 21103008,
        'tenphuongxa' => 'Xã Hồng Thái',
      ),
      8 =>
      array(
        'maphuongxa' => 21103009,
        'tenphuongxa' => 'Xã Nà Hang',
      ),
      9 =>
      array(
        'maphuongxa' => 21105010,
        'tenphuongxa' => 'Xã Tân Mỹ',
      ),
      10 =>
      array(
        'maphuongxa' => 21105011,
        'tenphuongxa' => 'Xã Yên Lập',
      ),
      11 =>
      array(
        'maphuongxa' => 21105012,
        'tenphuongxa' => 'Xã Tân An',
      ),
      12 =>
      array(
        'maphuongxa' => 21105013,
        'tenphuongxa' => 'Xã Chiêm Hoá',
      ),
      13 =>
      array(
        'maphuongxa' => 21105014,
        'tenphuongxa' => 'Xã Hoà An',
      ),
      14 =>
      array(
        'maphuongxa' => 21105015,
        'tenphuongxa' => 'Xã Kiên Đài',
      ),
      15 =>
      array(
        'maphuongxa' => 21105016,
        'tenphuongxa' => 'Xã Tri Phú',
      ),
      16 =>
      array(
        'maphuongxa' => 21105017,
        'tenphuongxa' => 'Xã Kim Bình',
      ),
      17 =>
      array(
        'maphuongxa' => 21105018,
        'tenphuongxa' => 'Xã Yên Nguyên',
      ),
      18 =>
      array(
        'maphuongxa' => 21105019,
        'tenphuongxa' => 'Xã Trung Hà',
      ),
      19 =>
      array(
        'maphuongxa' => 21107020,
        'tenphuongxa' => 'Xã Yên Phú',
      ),
      20 =>
      array(
        'maphuongxa' => 21107021,
        'tenphuongxa' => 'Xã Bạch Xa',
      ),
      21 =>
      array(
        'maphuongxa' => 21107022,
        'tenphuongxa' => 'Xã Phù Lưu',
      ),
      22 =>
      array(
        'maphuongxa' => 21107023,
        'tenphuongxa' => 'Xã Hàm Yên',
      ),
      23 =>
      array(
        'maphuongxa' => 21107024,
        'tenphuongxa' => 'Xã Bình Xa',
      ),
      24 =>
      array(
        'maphuongxa' => 21107025,
        'tenphuongxa' => 'Xã Thái Sơn',
      ),
      25 =>
      array(
        'maphuongxa' => 21107026,
        'tenphuongxa' => 'Xã Thái Hoà',
      ),
      26 =>
      array(
        'maphuongxa' => 21107027,
        'tenphuongxa' => 'Xã Hùng Đức',
      ),
      27 =>
      array(
        'maphuongxa' => 21109028,
        'tenphuongxa' => 'Xã Hùng Lợi',
      ),
      28 =>
      array(
        'maphuongxa' => 21109029,
        'tenphuongxa' => 'Xã Trung Sơn',
      ),
      29 =>
      array(
        'maphuongxa' => 21109030,
        'tenphuongxa' => 'Xã Thái Bình',
      ),
      30 =>
      array(
        'maphuongxa' => 21109031,
        'tenphuongxa' => 'Xã Tân Long',
      ),
      31 =>
      array(
        'maphuongxa' => 21109032,
        'tenphuongxa' => 'Xã Xuân Vân',
      ),
      32 =>
      array(
        'maphuongxa' => 21109033,
        'tenphuongxa' => 'Xã Lực Hành',
      ),
      33 =>
      array(
        'maphuongxa' => 21109034,
        'tenphuongxa' => 'Xã Yên Sơn',
      ),
      34 =>
      array(
        'maphuongxa' => 21109035,
        'tenphuongxa' => 'Xã Nhữ Khê',
      ),
      35 =>
      array(
        'maphuongxa' => 21109036,
        'tenphuongxa' => 'Xã Kiến Thiết',
      ),
      36 =>
      array(
        'maphuongxa' => 21111037,
        'tenphuongxa' => 'Xã Tân Trào',
      ),
      37 =>
      array(
        'maphuongxa' => 21111038,
        'tenphuongxa' => 'Xã Minh Thanh',
      ),
      38 =>
      array(
        'maphuongxa' => 21111039,
        'tenphuongxa' => 'Xã Sơn Dương',
      ),
      39 =>
      array(
        'maphuongxa' => 21111040,
        'tenphuongxa' => 'Xã Bình Ca',
      ),
      40 =>
      array(
        'maphuongxa' => 21111041,
        'tenphuongxa' => 'Xã Tân Thanh',
      ),
      41 =>
      array(
        'maphuongxa' => 21111042,
        'tenphuongxa' => 'Xã Sơn Thuỷ',
      ),
      42 =>
      array(
        'maphuongxa' => 21111043,
        'tenphuongxa' => 'Xã Phú Lương',
      ),
      43 =>
      array(
        'maphuongxa' => 21111044,
        'tenphuongxa' => 'Xã Trường Sinh',
      ),
      44 =>
      array(
        'maphuongxa' => 21111045,
        'tenphuongxa' => 'Xã Hồng Sơn',
      ),
      45 =>
      array(
        'maphuongxa' => 21111046,
        'tenphuongxa' => 'Xã Đông Thọ',
      ),
      46 =>
      array(
        'maphuongxa' => 21101047,
        'tenphuongxa' => 'Phường Mỹ Lâm',
      ),
      47 =>
      array(
        'maphuongxa' => 21101048,
        'tenphuongxa' => 'Phường Minh Xuân',
      ),
      48 =>
      array(
        'maphuongxa' => 21101049,
        'tenphuongxa' => 'Phường Nông Tiến',
      ),
      49 =>
      array(
        'maphuongxa' => 21101050,
        'tenphuongxa' => 'Phường An Tường',
      ),
      50 =>
      array(
        'maphuongxa' => 21101051,
        'tenphuongxa' => 'Phường Bình Thuận',
      ),
      51 =>
      array(
        'maphuongxa' => 20103052,
        'tenphuongxa' => 'Xã Lũng Cú',
      ),
      52 =>
      array(
        'maphuongxa' => 20103053,
        'tenphuongxa' => 'Xã Đồng Văn',
      ),
      53 =>
      array(
        'maphuongxa' => 20103054,
        'tenphuongxa' => 'Xã Sà Phìn',
      ),
      54 =>
      array(
        'maphuongxa' => 20103055,
        'tenphuongxa' => 'Xã Phố Bảng',
      ),
      55 =>
      array(
        'maphuongxa' => 20103056,
        'tenphuongxa' => 'Xã Lũng Phìn',
      ),
      56 =>
      array(
        'maphuongxa' => 20105057,
        'tenphuongxa' => 'Xã Sủng Máng',
      ),
      57 =>
      array(
        'maphuongxa' => 20105058,
        'tenphuongxa' => 'Xã Sơn Vĩ',
      ),
      58 =>
      array(
        'maphuongxa' => 20105059,
        'tenphuongxa' => 'Xã Mèo Vạc',
      ),
      59 =>
      array(
        'maphuongxa' => 20105060,
        'tenphuongxa' => 'Xã Khâu Vai',
      ),
      60 =>
      array(
        'maphuongxa' => 20105061,
        'tenphuongxa' => 'Xã Niêm Sơn',
      ),
      61 =>
      array(
        'maphuongxa' => 20105062,
        'tenphuongxa' => 'Xã Tát Ngà',
      ),
      62 =>
      array(
        'maphuongxa' => 20107063,
        'tenphuongxa' => 'Xã Thắng Mố',
      ),
      63 =>
      array(
        'maphuongxa' => 20107064,
        'tenphuongxa' => 'Xã Bạch Đích',
      ),
      64 =>
      array(
        'maphuongxa' => 20107065,
        'tenphuongxa' => 'Xã Yên Minh',
      ),
      65 =>
      array(
        'maphuongxa' => 20107066,
        'tenphuongxa' => 'Xã Mậu Duệ',
      ),
      66 =>
      array(
        'maphuongxa' => 20107067,
        'tenphuongxa' => 'Xã Ngọc Long',
      ),
      67 =>
      array(
        'maphuongxa' => 20107068,
        'tenphuongxa' => 'Xã Du Già',
      ),
      68 =>
      array(
        'maphuongxa' => 20107069,
        'tenphuongxa' => 'Xã Đường Thượng',
      ),
      69 =>
      array(
        'maphuongxa' => 20109070,
        'tenphuongxa' => 'Xã Lùng Tám',
      ),
      70 =>
      array(
        'maphuongxa' => 20109071,
        'tenphuongxa' => 'Xã Cán Tỷ',
      ),
      71 =>
      array(
        'maphuongxa' => 20109072,
        'tenphuongxa' => 'Xã Nghĩa Thuận',
      ),
      72 =>
      array(
        'maphuongxa' => 20109073,
        'tenphuongxa' => 'Xã Quản Bạ',
      ),
      73 =>
      array(
        'maphuongxa' => 20109074,
        'tenphuongxa' => 'Xã Tùng Vài',
      ),
      74 =>
      array(
        'maphuongxa' => 20111075,
        'tenphuongxa' => 'Xã Yên Cường',
      ),
      75 =>
      array(
        'maphuongxa' => 20111076,
        'tenphuongxa' => 'Xã Đường Hồng',
      ),
      76 =>
      array(
        'maphuongxa' => 20111077,
        'tenphuongxa' => 'Xã Bắc Mê',
      ),
      77 =>
      array(
        'maphuongxa' => 20111078,
        'tenphuongxa' => 'Xã Giáp Trung',
      ),
      78 =>
      array(
        'maphuongxa' => 20111079,
        'tenphuongxa' => 'Xã Minh Sơn',
      ),
      79 =>
      array(
        'maphuongxa' => 20111080,
        'tenphuongxa' => 'Xã Minh Ngọc',
      ),
      80 =>
      array(
        'maphuongxa' => 20101081,
        'tenphuongxa' => 'Xã Ngọc Đường',
      ),
      81 =>
      array(
        'maphuongxa' => 20101082,
        'tenphuongxa' => 'Phường Hà Giang 1',
      ),
      82 =>
      array(
        'maphuongxa' => 20101083,
        'tenphuongxa' => 'Phường Hà Giang 2',
      ),
      83 =>
      array(
        'maphuongxa' => 20115084,
        'tenphuongxa' => 'Xã Lao Chải',
      ),
      84 =>
      array(
        'maphuongxa' => 20115085,
        'tenphuongxa' => 'Xã Thanh Thuỷ',
      ),
      85 =>
      array(
        'maphuongxa' => 20115086,
        'tenphuongxa' => 'Xã Minh Tân',
      ),
      86 =>
      array(
        'maphuongxa' => 20115087,
        'tenphuongxa' => 'Xã Thuận Hoà',
      ),
      87 =>
      array(
        'maphuongxa' => 20115088,
        'tenphuongxa' => 'Xã Tùng Bá',
      ),
      88 =>
      array(
        'maphuongxa' => 20115089,
        'tenphuongxa' => 'Xã Phú Linh',
      ),
      89 =>
      array(
        'maphuongxa' => 20115090,
        'tenphuongxa' => 'Xã Linh Hồ',
      ),
      90 =>
      array(
        'maphuongxa' => 20115091,
        'tenphuongxa' => 'Xã Bạch Ngọc',
      ),
      91 =>
      array(
        'maphuongxa' => 20115092,
        'tenphuongxa' => 'Xã Vị Xuyên',
      ),
      92 =>
      array(
        'maphuongxa' => 20115093,
        'tenphuongxa' => 'Xã Việt Lâm',
      ),
      93 =>
      array(
        'maphuongxa' => 20115094,
        'tenphuongxa' => 'Xã Cao Bồ',
      ),
      94 =>
      array(
        'maphuongxa' => 20115095,
        'tenphuongxa' => 'Xã Thượng Sơn',
      ),
      95 =>
      array(
        'maphuongxa' => 20119096,
        'tenphuongxa' => 'Xã Tân Quang',
      ),
      96 =>
      array(
        'maphuongxa' => 20119097,
        'tenphuongxa' => 'Xã Đồng Tâm',
      ),
      97 =>
      array(
        'maphuongxa' => 20119098,
        'tenphuongxa' => 'Xã Liên Hiệp',
      ),
      98 =>
      array(
        'maphuongxa' => 20119099,
        'tenphuongxa' => 'Xã Bằng Hành',
      ),
      99 =>
      array(
        'maphuongxa' => 20119100,
        'tenphuongxa' => 'Xã Bắc Quang',
      ),
      100 =>
      array(
        'maphuongxa' => 20119101,
        'tenphuongxa' => 'Xã Hùng An',
      ),
      101 =>
      array(
        'maphuongxa' => 20119102,
        'tenphuongxa' => 'Xã Vĩnh Tuy',
      ),
      102 =>
      array(
        'maphuongxa' => 20119103,
        'tenphuongxa' => 'Xã Đồng Yên',
      ),
      103 =>
      array(
        'maphuongxa' => 20118104,
        'tenphuongxa' => 'Xã Tiên Yên',
      ),
      104 =>
      array(
        'maphuongxa' => 20118105,
        'tenphuongxa' => 'Xã Xuân Giang',
      ),
      105 =>
      array(
        'maphuongxa' => 20118106,
        'tenphuongxa' => 'Xã Bằng Lang',
      ),
      106 =>
      array(
        'maphuongxa' => 20118107,
        'tenphuongxa' => 'Xã Yên Thành',
      ),
      107 =>
      array(
        'maphuongxa' => 20118108,
        'tenphuongxa' => 'Xã Quang Bình',
      ),
      108 =>
      array(
        'maphuongxa' => 20118109,
        'tenphuongxa' => 'Xã Tân Trịnh',
      ),
      109 =>
      array(
        'maphuongxa' => 20118110,
        'tenphuongxa' => 'Xã Tiên Nguyên',
      ),
      110 =>
      array(
        'maphuongxa' => 20113111,
        'tenphuongxa' => 'Xã Thông Nguyên',
      ),
      111 =>
      array(
        'maphuongxa' => 20113112,
        'tenphuongxa' => 'Xã Hồ Thầu',
      ),
      112 =>
      array(
        'maphuongxa' => 20113113,
        'tenphuongxa' => 'Xã Nậm Dịch',
      ),
      113 =>
      array(
        'maphuongxa' => 20113114,
        'tenphuongxa' => 'Xã Tân Tiến',
      ),
      114 =>
      array(
        'maphuongxa' => 20113115,
        'tenphuongxa' => 'Xã Hoàng Su Phì',
      ),
      115 =>
      array(
        'maphuongxa' => 20113116,
        'tenphuongxa' => 'Xã Thàng Tín',
      ),
      116 =>
      array(
        'maphuongxa' => 20113117,
        'tenphuongxa' => 'Xã Bản Máy',
      ),
      117 =>
      array(
        'maphuongxa' => 20113118,
        'tenphuongxa' => 'Xã Pờ Ly Ngài',
      ),
      118 =>
      array(
        'maphuongxa' => 20117119,
        'tenphuongxa' => 'Xã Xín Mần',
      ),
      119 =>
      array(
        'maphuongxa' => 20117120,
        'tenphuongxa' => 'Xã Pà Vầy Sủ',
      ),
      120 =>
      array(
        'maphuongxa' => 20117121,
        'tenphuongxa' => 'Xã Nấm Dẩn',
      ),
      121 =>
      array(
        'maphuongxa' => 20117122,
        'tenphuongxa' => 'Xã Trung Thịnh',
      ),
      122 =>
      array(
        'maphuongxa' => 20117123,
        'tenphuongxa' => 'Xã Quảng Nguyên',
      ),
      123 =>
      array(
        'maphuongxa' => 20117124,
        'tenphuongxa' => 'Xã Khuôn Lùng',
      ),
    ),
  ),
  8 =>
  array(
    'matinhBNV' => '09',
    'matinhTMS' => '205',
    'tentinhmoi' => 'Tỉnh Lào Cai',
    'phuongxa' =>
    array(
      0 =>
      array(
        'maphuongxa' => 21309001,
        'tenphuongxa' => 'Xã Khao Mang',
      ),
      1 =>
      array(
        'maphuongxa' => 21309002,
        'tenphuongxa' => 'Xã Mù Cang Chải',
      ),
      2 =>
      array(
        'maphuongxa' => 21309003,
        'tenphuongxa' => 'Xã Púng Luông',
      ),
      3 =>
      array(
        'maphuongxa' => 21315004,
        'tenphuongxa' => 'Xã Tú Lệ',
      ),
      4 =>
      array(
        'maphuongxa' => 21317005,
        'tenphuongxa' => 'Xã Trạm Tấu',
      ),
      5 =>
      array(
        'maphuongxa' => 21317006,
        'tenphuongxa' => 'Xã Hạnh Phúc',
      ),
      6 =>
      array(
        'maphuongxa' => 21317007,
        'tenphuongxa' => 'Xã Phình Hồ',
      ),
      7 =>
      array(
        'maphuongxa' => 21303008,
        'tenphuongxa' => 'Phường Nghĩa Lộ',
      ),
      8 =>
      array(
        'maphuongxa' => 21303009,
        'tenphuongxa' => 'Phường Trung Tâm',
      ),
      9 =>
      array(
        'maphuongxa' => 21303010,
        'tenphuongxa' => 'Phường Cầu Thia',
      ),
      10 =>
      array(
        'maphuongxa' => 21303011,
        'tenphuongxa' => 'Xã Liên Sơn',
      ),
      11 =>
      array(
        'maphuongxa' => 21315012,
        'tenphuongxa' => 'Xã Gia Hội',
      ),
      12 =>
      array(
        'maphuongxa' => 21315013,
        'tenphuongxa' => 'Xã Sơn Lương',
      ),
      13 =>
      array(
        'maphuongxa' => 21315014,
        'tenphuongxa' => 'Xã Thượng Bằng La',
      ),
      14 =>
      array(
        'maphuongxa' => 21315015,
        'tenphuongxa' => 'Xã Chấn Thịnh',
      ),
      15 =>
      array(
        'maphuongxa' => 21315016,
        'tenphuongxa' => 'Xã Nghĩa Tâm',
      ),
      16 =>
      array(
        'maphuongxa' => 21315017,
        'tenphuongxa' => 'Xã Văn Chấn',
      ),
      17 =>
      array(
        'maphuongxa' => 21307018,
        'tenphuongxa' => 'Xã Phong Dụ Hạ',
      ),
      18 =>
      array(
        'maphuongxa' => 21307019,
        'tenphuongxa' => 'Xã Châu Quế',
      ),
      19 =>
      array(
        'maphuongxa' => 21307020,
        'tenphuongxa' => 'Xã Lâm Giang',
      ),
      20 =>
      array(
        'maphuongxa' => 21307021,
        'tenphuongxa' => 'Xã Đông Cuông',
      ),
      21 =>
      array(
        'maphuongxa' => 21307022,
        'tenphuongxa' => 'Xã Tân Hợp',
      ),
      22 =>
      array(
        'maphuongxa' => 21307023,
        'tenphuongxa' => 'Xã Mậu A',
      ),
      23 =>
      array(
        'maphuongxa' => 21307024,
        'tenphuongxa' => 'Xã Xuân Ái',
      ),
      24 =>
      array(
        'maphuongxa' => 21307025,
        'tenphuongxa' => 'Xã Mỏ Vàng',
      ),
      25 =>
      array(
        'maphuongxa' => 21305026,
        'tenphuongxa' => 'Xã Lâm Thượng',
      ),
      26 =>
      array(
        'maphuongxa' => 21305027,
        'tenphuongxa' => 'Xã Lục Yên',
      ),
      27 =>
      array(
        'maphuongxa' => 21305028,
        'tenphuongxa' => 'Xã Tân Lĩnh',
      ),
      28 =>
      array(
        'maphuongxa' => 21305029,
        'tenphuongxa' => 'Xã Khánh Hoà',
      ),
      29 =>
      array(
        'maphuongxa' => 21305030,
        'tenphuongxa' => 'Xã Phúc Lợi',
      ),
      30 =>
      array(
        'maphuongxa' => 21305031,
        'tenphuongxa' => 'Xã Mường Lai',
      ),
      31 =>
      array(
        'maphuongxa' => 21313032,
        'tenphuongxa' => 'Xã Cảm Nhân',
      ),
      32 =>
      array(
        'maphuongxa' => 21313033,
        'tenphuongxa' => 'Xã Yên Thành',
      ),
      33 =>
      array(
        'maphuongxa' => 21313034,
        'tenphuongxa' => 'Xã Thác Bà',
      ),
      34 =>
      array(
        'maphuongxa' => 21313035,
        'tenphuongxa' => 'Xã Yên Bình',
      ),
      35 =>
      array(
        'maphuongxa' => 21313036,
        'tenphuongxa' => 'Xã Bảo Ái',
      ),
      36 =>
      array(
        'maphuongxa' => 21301037,
        'tenphuongxa' => 'Phường Văn Phú',
      ),
      37 =>
      array(
        'maphuongxa' => 21301038,
        'tenphuongxa' => 'Phường Yên Bái',
      ),
      38 =>
      array(
        'maphuongxa' => 21301039,
        'tenphuongxa' => 'Phường Nam Cường',
      ),
      39 =>
      array(
        'maphuongxa' => 21301040,
        'tenphuongxa' => 'Phường Âu Lâu',
      ),
      40 =>
      array(
        'maphuongxa' => 21311041,
        'tenphuongxa' => 'Xã Trấn Yên',
      ),
      41 =>
      array(
        'maphuongxa' => 21311042,
        'tenphuongxa' => 'Xã Hưng Khánh',
      ),
      42 =>
      array(
        'maphuongxa' => 21311043,
        'tenphuongxa' => 'Xã Lương Thịnh',
      ),
      43 =>
      array(
        'maphuongxa' => 21311044,
        'tenphuongxa' => 'Xã Việt Hồng',
      ),
      44 =>
      array(
        'maphuongxa' => 21311045,
        'tenphuongxa' => 'Xã Quy Mông',
      ),
      45 =>
      array(
        'maphuongxa' => 20511046,
        'tenphuongxa' => 'Xã Phong Hải',
      ),
      46 =>
      array(
        'maphuongxa' => 20511047,
        'tenphuongxa' => 'Xã Xuân Quang',
      ),
      47 =>
      array(
        'maphuongxa' => 20511048,
        'tenphuongxa' => 'Xã Bảo Thắng',
      ),
      48 =>
      array(
        'maphuongxa' => 20511049,
        'tenphuongxa' => 'Xã Tằng Lỏong',
      ),
      49 =>
      array(
        'maphuongxa' => 20511050,
        'tenphuongxa' => 'Xã Gia Phú',
      ),
      50 =>
      array(
        'maphuongxa' => 20501051,
        'tenphuongxa' => 'Xã Cốc San',
      ),
      51 =>
      array(
        'maphuongxa' => 20501052,
        'tenphuongxa' => 'Xã Hợp Thành',
      ),
      52 =>
      array(
        'maphuongxa' => 20501053,
        'tenphuongxa' => 'Phường Cam Đường',
      ),
      53 =>
      array(
        'maphuongxa' => 20501054,
        'tenphuongxa' => 'Phường Lào Cai',
      ),
      54 =>
      array(
        'maphuongxa' => 20507055,
        'tenphuongxa' => 'Xã Mường Hum',
      ),
      55 =>
      array(
        'maphuongxa' => 20507056,
        'tenphuongxa' => 'Xã Dền Sáng',
      ),
      56 =>
      array(
        'maphuongxa' => 20507057,
        'tenphuongxa' => 'Xã Y Tý',
      ),
      57 =>
      array(
        'maphuongxa' => 20507058,
        'tenphuongxa' => 'Xã A Mú Sung',
      ),
      58 =>
      array(
        'maphuongxa' => 20507059,
        'tenphuongxa' => 'Xã Trịnh Tường',
      ),
      59 =>
      array(
        'maphuongxa' => 20507060,
        'tenphuongxa' => 'Xã Bản Xèo',
      ),
      60 =>
      array(
        'maphuongxa' => 20507061,
        'tenphuongxa' => 'Xã Bát Xát',
      ),
      61 =>
      array(
        'maphuongxa' => 20515062,
        'tenphuongxa' => 'Xã Nghĩa Đô',
      ),
      62 =>
      array(
        'maphuongxa' => 20515063,
        'tenphuongxa' => 'Xã Thượng Hà',
      ),
      63 =>
      array(
        'maphuongxa' => 20515064,
        'tenphuongxa' => 'Xã Bảo Yên',
      ),
      64 =>
      array(
        'maphuongxa' => 20515065,
        'tenphuongxa' => 'Xã Xuân Hoà',
      ),
      65 =>
      array(
        'maphuongxa' => 20515066,
        'tenphuongxa' => 'Xã Phúc Khánh',
      ),
      66 =>
      array(
        'maphuongxa' => 20515067,
        'tenphuongxa' => 'Xã Bảo Hà',
      ),
      67 =>
      array(
        'maphuongxa' => 20519068,
        'tenphuongxa' => 'Xã Võ Lao',
      ),
      68 =>
      array(
        'maphuongxa' => 20519069,
        'tenphuongxa' => 'Xã Khánh Yên',
      ),
      69 =>
      array(
        'maphuongxa' => 20519070,
        'tenphuongxa' => 'Xã Văn Bàn',
      ),
      70 =>
      array(
        'maphuongxa' => 20519071,
        'tenphuongxa' => 'Xã Dương Quỳ',
      ),
      71 =>
      array(
        'maphuongxa' => 20519072,
        'tenphuongxa' => 'Xã Chiềng Ken',
      ),
      72 =>
      array(
        'maphuongxa' => 20519073,
        'tenphuongxa' => 'Xã Minh Lương',
      ),
      73 =>
      array(
        'maphuongxa' => 20519074,
        'tenphuongxa' => 'Xã Nậm Chày',
      ),
      74 =>
      array(
        'maphuongxa' => 20513075,
        'tenphuongxa' => 'Xã Mường Bo',
      ),
      75 =>
      array(
        'maphuongxa' => 20513076,
        'tenphuongxa' => 'Xã Bản Hồ',
      ),
      76 =>
      array(
        'maphuongxa' => 20513077,
        'tenphuongxa' => 'Xã Tả Phìn',
      ),
      77 =>
      array(
        'maphuongxa' => 20513078,
        'tenphuongxa' => 'Xã Tả Van',
      ),
      78 =>
      array(
        'maphuongxa' => 20513079,
        'tenphuongxa' => 'Phường Sa Pa',
      ),
      79 =>
      array(
        'maphuongxa' => 20509080,
        'tenphuongxa' => 'Xã Cốc Lầu',
      ),
      80 =>
      array(
        'maphuongxa' => 20509081,
        'tenphuongxa' => 'Xã Bảo Nhai',
      ),
      81 =>
      array(
        'maphuongxa' => 20509082,
        'tenphuongxa' => 'Xã Bản Liền',
      ),
      82 =>
      array(
        'maphuongxa' => 20509083,
        'tenphuongxa' => 'Xã Bắc Hà',
      ),
      83 =>
      array(
        'maphuongxa' => 20509084,
        'tenphuongxa' => 'Xã Tả Củ Tỷ',
      ),
      84 =>
      array(
        'maphuongxa' => 20509085,
        'tenphuongxa' => 'Xã Lùng Phình',
      ),
      85 =>
      array(
        'maphuongxa' => 20505086,
        'tenphuongxa' => 'Xã Pha Long',
      ),
      86 =>
      array(
        'maphuongxa' => 20505087,
        'tenphuongxa' => 'Xã Mường Khương',
      ),
      87 =>
      array(
        'maphuongxa' => 20505088,
        'tenphuongxa' => 'Xã Bản Lầu',
      ),
      88 =>
      array(
        'maphuongxa' => 20505089,
        'tenphuongxa' => 'Xã Cao Sơn',
      ),
      89 =>
      array(
        'maphuongxa' => 20521090,
        'tenphuongxa' => 'Xã Si Ma Cai',
      ),
      90 =>
      array(
        'maphuongxa' => 20521091,
        'tenphuongxa' => 'Xã Sín Chéng',
      ),
      91 =>
      array(
        'maphuongxa' => 21309092,
        'tenphuongxa' => 'Xã Lao Chải',
      ),
      92 =>
      array(
        'maphuongxa' => 21309093,
        'tenphuongxa' => 'Xã Chế Tạo',
      ),
      93 =>
      array(
        'maphuongxa' => 21309094,
        'tenphuongxa' => 'Xã Nậm Có',
      ),
      94 =>
      array(
        'maphuongxa' => 21317095,
        'tenphuongxa' => 'Xã Tà Xi Láng',
      ),
      95 =>
      array(
        'maphuongxa' => 21307096,
        'tenphuongxa' => 'Xã Phong Dụ Thượng',
      ),
      96 =>
      array(
        'maphuongxa' => 21315097,
        'tenphuongxa' => 'Xã Cát Thịnh',
      ),
      97 =>
      array(
        'maphuongxa' => 20519098,
        'tenphuongxa' => 'Xã Nậm Xé',
      ),
      98 =>
      array(
        'maphuongxa' => 20513099,
        'tenphuongxa' => 'Xã Ngũ Chỉ Sơn',
      ),
    ),
  ),
  9 =>
  array(
    'matinhBNV' => 10,
    'matinhTMS' => '215',
    'tentinhmoi' => 'Tỉnh Thái Nguyên',
    'phuongxa' =>
    array(
      0 =>
      array(
        'maphuongxa' => 21501001,
        'tenphuongxa' => 'Phường Phan Đình Phùng',
      ),
      1 =>
      array(
        'maphuongxa' => 21501002,
        'tenphuongxa' => 'Phường Linh Sơn',
      ),
      2 =>
      array(
        'maphuongxa' => 21501003,
        'tenphuongxa' => 'Phường Tích Lương',
      ),
      3 =>
      array(
        'maphuongxa' => 21501004,
        'tenphuongxa' => 'Phường Gia Sàng',
      ),
      4 =>
      array(
        'maphuongxa' => 21501005,
        'tenphuongxa' => 'Phường Quyết Thắng',
      ),
      5 =>
      array(
        'maphuongxa' => 21501006,
        'tenphuongxa' => 'Phường Quan Triều',
      ),
      6 =>
      array(
        'maphuongxa' => 21501007,
        'tenphuongxa' => 'Xã Tân Cương',
      ),
      7 =>
      array(
        'maphuongxa' => 21501008,
        'tenphuongxa' => 'Xã Đại Phúc',
      ),
      8 =>
      array(
        'maphuongxa' => 21513009,
        'tenphuongxa' => 'Xã Đại Từ',
      ),
      9 =>
      array(
        'maphuongxa' => 21513010,
        'tenphuongxa' => 'Xã Đức Lương',
      ),
      10 =>
      array(
        'maphuongxa' => 21513011,
        'tenphuongxa' => 'Xã Phú Thịnh',
      ),
      11 =>
      array(
        'maphuongxa' => 21513012,
        'tenphuongxa' => 'Xã La Bằng',
      ),
      12 =>
      array(
        'maphuongxa' => 21513013,
        'tenphuongxa' => 'Xã Phú Lạc',
      ),
      13 =>
      array(
        'maphuongxa' => 21513014,
        'tenphuongxa' => 'Xã An Khánh',
      ),
      14 =>
      array(
        'maphuongxa' => 21513015,
        'tenphuongxa' => 'Xã Quân Chu',
      ),
      15 =>
      array(
        'maphuongxa' => 21513016,
        'tenphuongxa' => 'Xã Vạn Phú',
      ),
      16 =>
      array(
        'maphuongxa' => 21513017,
        'tenphuongxa' => 'Xã Phú Xuyên',
      ),
      17 =>
      array(
        'maphuongxa' => 21517018,
        'tenphuongxa' => 'Phường Phổ Yên',
      ),
      18 =>
      array(
        'maphuongxa' => 21517019,
        'tenphuongxa' => 'Phường Vạn Xuân',
      ),
      19 =>
      array(
        'maphuongxa' => 21517020,
        'tenphuongxa' => 'Phường Trung Thành',
      ),
      20 =>
      array(
        'maphuongxa' => 21517021,
        'tenphuongxa' => 'Phường Phúc Thuận',
      ),
      21 =>
      array(
        'maphuongxa' => 21517022,
        'tenphuongxa' => 'Xã Thành Công',
      ),
      22 =>
      array(
        'maphuongxa' => 21515023,
        'tenphuongxa' => 'Xã Phú Bình',
      ),
      23 =>
      array(
        'maphuongxa' => 21515024,
        'tenphuongxa' => 'Xã Tân Thành',
      ),
      24 =>
      array(
        'maphuongxa' => 21515025,
        'tenphuongxa' => 'Xã Điềm Thụy',
      ),
      25 =>
      array(
        'maphuongxa' => 21515026,
        'tenphuongxa' => 'Xã Kha Sơn',
      ),
      26 =>
      array(
        'maphuongxa' => 21515027,
        'tenphuongxa' => 'Xã Tân Khánh',
      ),
      27 =>
      array(
        'maphuongxa' => 21511028,
        'tenphuongxa' => 'Xã Đồng Hỷ',
      ),
      28 =>
      array(
        'maphuongxa' => 21511029,
        'tenphuongxa' => 'Xã Quang Sơn',
      ),
      29 =>
      array(
        'maphuongxa' => 21511030,
        'tenphuongxa' => 'Xã Trại Cau',
      ),
      30 =>
      array(
        'maphuongxa' => 21511031,
        'tenphuongxa' => 'Xã Nam Hoà',
      ),
      31 =>
      array(
        'maphuongxa' => 21511032,
        'tenphuongxa' => 'Xã Văn Hán',
      ),
      32 =>
      array(
        'maphuongxa' => 21511033,
        'tenphuongxa' => 'Xã Văn Lăng',
      ),
      33 =>
      array(
        'maphuongxa' => 21503034,
        'tenphuongxa' => 'Phường Sông Công',
      ),
      34 =>
      array(
        'maphuongxa' => 21503035,
        'tenphuongxa' => 'Phường Bá Xuyên',
      ),
      35 =>
      array(
        'maphuongxa' => 21503036,
        'tenphuongxa' => 'Phường Bách Quang',
      ),
      36 =>
      array(
        'maphuongxa' => 21509037,
        'tenphuongxa' => 'Xã Phú Lương',
      ),
      37 =>
      array(
        'maphuongxa' => 21509038,
        'tenphuongxa' => 'Xã Vô Tranh',
      ),
      38 =>
      array(
        'maphuongxa' => 21509039,
        'tenphuongxa' => 'Xã Yên Trạch',
      ),
      39 =>
      array(
        'maphuongxa' => 21509040,
        'tenphuongxa' => 'Xã Hợp Thành',
      ),
      40 =>
      array(
        'maphuongxa' => 21505041,
        'tenphuongxa' => 'Xã Định Hóa',
      ),
      41 =>
      array(
        'maphuongxa' => 21505042,
        'tenphuongxa' => 'Xã Bình Yên',
      ),
      42 =>
      array(
        'maphuongxa' => 21505043,
        'tenphuongxa' => 'Xã Trung Hội',
      ),
      43 =>
      array(
        'maphuongxa' => 21505044,
        'tenphuongxa' => 'Xã Phượng Tiến',
      ),
      44 =>
      array(
        'maphuongxa' => 21505045,
        'tenphuongxa' => 'Xã Phú Đình',
      ),
      45 =>
      array(
        'maphuongxa' => 21505046,
        'tenphuongxa' => 'Xã Bình Thành',
      ),
      46 =>
      array(
        'maphuongxa' => 21505047,
        'tenphuongxa' => 'Xã Kim Phượng',
      ),
      47 =>
      array(
        'maphuongxa' => 21505048,
        'tenphuongxa' => 'Xã Lam Vỹ',
      ),
      48 =>
      array(
        'maphuongxa' => 21507049,
        'tenphuongxa' => 'Xã Võ Nhai',
      ),
      49 =>
      array(
        'maphuongxa' => 21507050,
        'tenphuongxa' => 'Xã Dân Tiến',
      ),
      50 =>
      array(
        'maphuongxa' => 21507051,
        'tenphuongxa' => 'Xã Nghinh Tường',
      ),
      51 =>
      array(
        'maphuongxa' => 21507052,
        'tenphuongxa' => 'Xã Thần Sa',
      ),
      52 =>
      array(
        'maphuongxa' => 21507053,
        'tenphuongxa' => 'Xã La Hiên',
      ),
      53 =>
      array(
        'maphuongxa' => 21507054,
        'tenphuongxa' => 'Xã Tràng Xá',
      ),
      54 =>
      array(
        'maphuongxa' => 20704055,
        'tenphuongxa' => 'Xã Bằng Thành',
      ),
      55 =>
      array(
        'maphuongxa' => 20704056,
        'tenphuongxa' => 'Xã Nghiên Loan',
      ),
      56 =>
      array(
        'maphuongxa' => 20704057,
        'tenphuongxa' => 'Xã Cao Minh',
      ),
      57 =>
      array(
        'maphuongxa' => 20703058,
        'tenphuongxa' => 'Xã Ba Bể',
      ),
      58 =>
      array(
        'maphuongxa' => 20703059,
        'tenphuongxa' => 'Xã Chợ Rã',
      ),
      59 =>
      array(
        'maphuongxa' => 20703060,
        'tenphuongxa' => 'Xã Phúc Lộc',
      ),
      60 =>
      array(
        'maphuongxa' => 20703061,
        'tenphuongxa' => 'Xã Thượng Minh',
      ),
      61 =>
      array(
        'maphuongxa' => 20703062,
        'tenphuongxa' => 'Xã Đồng Phúc',
      ),
      62 =>
      array(
        'maphuongxa' => 20713063,
        'tenphuongxa' => 'Xã Yên Bình',
      ),
      63 =>
      array(
        'maphuongxa' => 20705064,
        'tenphuongxa' => 'Xã Bằng Vân',
      ),
      64 =>
      array(
        'maphuongxa' => 20705065,
        'tenphuongxa' => 'Xã Ngân Sơn',
      ),
      65 =>
      array(
        'maphuongxa' => 20705066,
        'tenphuongxa' => 'Xã Nà Phặc',
      ),
      66 =>
      array(
        'maphuongxa' => 20705067,
        'tenphuongxa' => 'Xã Hiệp Lực',
      ),
      67 =>
      array(
        'maphuongxa' => 20707068,
        'tenphuongxa' => 'Xã Nam Cường',
      ),
      68 =>
      array(
        'maphuongxa' => 20707069,
        'tenphuongxa' => 'Xã Quảng Bạch',
      ),
      69 =>
      array(
        'maphuongxa' => 20707070,
        'tenphuongxa' => 'Xã Yên Thịnh',
      ),
      70 =>
      array(
        'maphuongxa' => 20707071,
        'tenphuongxa' => 'Xã Chợ Đồn',
      ),
      71 =>
      array(
        'maphuongxa' => 20707072,
        'tenphuongxa' => 'Xã Yên Phong',
      ),
      72 =>
      array(
        'maphuongxa' => 20707073,
        'tenphuongxa' => 'Xã Nghĩa Tá',
      ),
      73 =>
      array(
        'maphuongxa' => 20711074,
        'tenphuongxa' => 'Xã Phủ Thông',
      ),
      74 =>
      array(
        'maphuongxa' => 20711075,
        'tenphuongxa' => 'Xã Cẩm Giàng',
      ),
      75 =>
      array(
        'maphuongxa' => 20711076,
        'tenphuongxa' => 'Xã Vĩnh Thông',
      ),
      76 =>
      array(
        'maphuongxa' => 20711077,
        'tenphuongxa' => 'Xã Bạch Thông',
      ),
      77 =>
      array(
        'maphuongxa' => 20701078,
        'tenphuongxa' => 'Xã Phong Quang',
      ),
      78 =>
      array(
        'maphuongxa' => 20701079,
        'tenphuongxa' => 'Phường Đức Xuân',
      ),
      79 =>
      array(
        'maphuongxa' => 20701080,
        'tenphuongxa' => 'Phường Bắc Kạn',
      ),
      80 =>
      array(
        'maphuongxa' => 20709081,
        'tenphuongxa' => 'Xã Văn Lang',
      ),
      81 =>
      array(
        'maphuongxa' => 20709082,
        'tenphuongxa' => 'Xã Cường Lợi',
      ),
      82 =>
      array(
        'maphuongxa' => 20709083,
        'tenphuongxa' => 'Xã Na Rì',
      ),
      83 =>
      array(
        'maphuongxa' => 20709084,
        'tenphuongxa' => 'Xã Trần Phú',
      ),
      84 =>
      array(
        'maphuongxa' => 20709085,
        'tenphuongxa' => 'Xã Côn Minh',
      ),
      85 =>
      array(
        'maphuongxa' => 20709086,
        'tenphuongxa' => 'Xã Xuân Dương',
      ),
      86 =>
      array(
        'maphuongxa' => 20713087,
        'tenphuongxa' => 'Xã Tân Kỳ',
      ),
      87 =>
      array(
        'maphuongxa' => 20713088,
        'tenphuongxa' => 'Xã Thanh Mai',
      ),
      88 =>
      array(
        'maphuongxa' => 20713089,
        'tenphuongxa' => 'Xã Thanh Thịnh',
      ),
      89 =>
      array(
        'maphuongxa' => 20713090,
        'tenphuongxa' => 'Xã Chợ Mới',
      ),
      90 =>
      array(
        'maphuongxa' => 21507091,
        'tenphuongxa' => 'Xã Sảng Mộc',
      ),
      91 =>
      array(
        'maphuongxa' => 20705092,
        'tenphuongxa' => 'Xã Thượng Quan',
      ),
    ),
  ),
  10 =>
  array(
    'matinhBNV' => 11,
    'matinhTMS' => '209',
    'tentinhmoi' => 'Tỉnh Lạng Sơn',
    'phuongxa' =>
    array(
      0 =>
      array(
        'maphuongxa' => 20903001,
        'tenphuongxa' => 'Xã Thất Khê',
      ),
      1 =>
      array(
        'maphuongxa' => 20903002,
        'tenphuongxa' => 'Xã Đoàn Kết',
      ),
      2 =>
      array(
        'maphuongxa' => 20903003,
        'tenphuongxa' => 'Xã Tân Tiến',
      ),
      3 =>
      array(
        'maphuongxa' => 20903004,
        'tenphuongxa' => 'Xã Tràng Định',
      ),
      4 =>
      array(
        'maphuongxa' => 20903005,
        'tenphuongxa' => 'Xã Quốc Khánh',
      ),
      5 =>
      array(
        'maphuongxa' => 20903006,
        'tenphuongxa' => 'Xã Kháng Chiến',
      ),
      6 =>
      array(
        'maphuongxa' => 20903007,
        'tenphuongxa' => 'Xã Quốc Việt',
      ),
      7 =>
      array(
        'maphuongxa' => 20907008,
        'tenphuongxa' => 'Xã Bình Gia',
      ),
      8 =>
      array(
        'maphuongxa' => 20907009,
        'tenphuongxa' => 'Xã Tân Văn',
      ),
      9 =>
      array(
        'maphuongxa' => 20907010,
        'tenphuongxa' => 'Xã Hồng Phong',
      ),
      10 =>
      array(
        'maphuongxa' => 20907011,
        'tenphuongxa' => 'Xã Hoa Thám',
      ),
      11 =>
      array(
        'maphuongxa' => 20907012,
        'tenphuongxa' => 'Xã Quý Hoà',
      ),
      12 =>
      array(
        'maphuongxa' => 20907013,
        'tenphuongxa' => 'Xã Thiện Hoà',
      ),
      13 =>
      array(
        'maphuongxa' => 20907014,
        'tenphuongxa' => 'Xã Thiện Thuật',
      ),
      14 =>
      array(
        'maphuongxa' => 20907015,
        'tenphuongxa' => 'Xã Thiện Long',
      ),
      15 =>
      array(
        'maphuongxa' => 20909016,
        'tenphuongxa' => 'Xã Bắc Sơn',
      ),
      16 =>
      array(
        'maphuongxa' => 20909017,
        'tenphuongxa' => 'Xã Hưng Vũ',
      ),
      17 =>
      array(
        'maphuongxa' => 20909018,
        'tenphuongxa' => 'Xã Vũ Lăng',
      ),
      18 =>
      array(
        'maphuongxa' => 20909019,
        'tenphuongxa' => 'Xã Nhất Hoà',
      ),
      19 =>
      array(
        'maphuongxa' => 20909020,
        'tenphuongxa' => 'Xã Vũ Lễ',
      ),
      20 =>
      array(
        'maphuongxa' => 20909021,
        'tenphuongxa' => 'Xã Tân Tri',
      ),
      21 =>
      array(
        'maphuongxa' => 20911022,
        'tenphuongxa' => 'Xã Văn Quan',
      ),
      22 =>
      array(
        'maphuongxa' => 20911023,
        'tenphuongxa' => 'Xã Điềm He',
      ),
      23 =>
      array(
        'maphuongxa' => 20911024,
        'tenphuongxa' => 'Xã Tri Lễ',
      ),
      24 =>
      array(
        'maphuongxa' => 20911025,
        'tenphuongxa' => 'Xã Yên Phúc',
      ),
      25 =>
      array(
        'maphuongxa' => 20911026,
        'tenphuongxa' => 'Xã Tân Đoàn',
      ),
      26 =>
      array(
        'maphuongxa' => 20913027,
        'tenphuongxa' => 'Xã Khánh Khê',
      ),
      27 =>
      array(
        'maphuongxa' => 20905028,
        'tenphuongxa' => 'Xã Na Sầm',
      ),
      28 =>
      array(
        'maphuongxa' => 20905029,
        'tenphuongxa' => 'Xã Văn Lãng',
      ),
      29 =>
      array(
        'maphuongxa' => 20905030,
        'tenphuongxa' => 'Xã Hội Hoan',
      ),
      30 =>
      array(
        'maphuongxa' => 20905031,
        'tenphuongxa' => 'Xã Thụy Hùng',
      ),
      31 =>
      array(
        'maphuongxa' => 20905032,
        'tenphuongxa' => 'Xã Hoàng Văn Thụ',
      ),
      32 =>
      array(
        'maphuongxa' => 20915033,
        'tenphuongxa' => 'Xã Lộc Bình',
      ),
      33 =>
      array(
        'maphuongxa' => 20915034,
        'tenphuongxa' => 'Xã Mẫu Sơn',
      ),
      34 =>
      array(
        'maphuongxa' => 20915035,
        'tenphuongxa' => 'Xã Na Dương',
      ),
      35 =>
      array(
        'maphuongxa' => 20915036,
        'tenphuongxa' => 'Xã Lợi Bác',
      ),
      36 =>
      array(
        'maphuongxa' => 20915037,
        'tenphuongxa' => 'Xã Thống Nhất',
      ),
      37 =>
      array(
        'maphuongxa' => 20915038,
        'tenphuongxa' => 'Xã Xuân Dương',
      ),
      38 =>
      array(
        'maphuongxa' => 20915039,
        'tenphuongxa' => 'Xã Khuất Xá',
      ),
      39 =>
      array(
        'maphuongxa' => 20919040,
        'tenphuongxa' => 'Xã Đình Lập',
      ),
      40 =>
      array(
        'maphuongxa' => 20919041,
        'tenphuongxa' => 'Xã Châu Sơn',
      ),
      41 =>
      array(
        'maphuongxa' => 20919042,
        'tenphuongxa' => 'Xã Kiên Mộc',
      ),
      42 =>
      array(
        'maphuongxa' => 20919043,
        'tenphuongxa' => 'Xã Thái Bình',
      ),
      43 =>
      array(
        'maphuongxa' => 20921044,
        'tenphuongxa' => 'Xã Hữu Lũng',
      ),
      44 =>
      array(
        'maphuongxa' => 20921045,
        'tenphuongxa' => 'Xã Tuấn Sơn',
      ),
      45 =>
      array(
        'maphuongxa' => 20921046,
        'tenphuongxa' => 'Xã Tân Thành',
      ),
      46 =>
      array(
        'maphuongxa' => 20921047,
        'tenphuongxa' => 'Xã Vân Nham',
      ),
      47 =>
      array(
        'maphuongxa' => 20921048,
        'tenphuongxa' => 'Xã Thiện Tân',
      ),
      48 =>
      array(
        'maphuongxa' => 20921049,
        'tenphuongxa' => 'Xã Yên Bình',
      ),
      49 =>
      array(
        'maphuongxa' => 20921050,
        'tenphuongxa' => 'Xã Hữu Liên',
      ),
      50 =>
      array(
        'maphuongxa' => 20921051,
        'tenphuongxa' => 'Xã Cai Kinh',
      ),
      51 =>
      array(
        'maphuongxa' => 20917052,
        'tenphuongxa' => 'Xã Chi Lăng',
      ),
      52 =>
      array(
        'maphuongxa' => 20917053,
        'tenphuongxa' => 'Xã Nhân Lý',
      ),
      53 =>
      array(
        'maphuongxa' => 20917054,
        'tenphuongxa' => 'Xã Chiến Thắng',
      ),
      54 =>
      array(
        'maphuongxa' => 20917055,
        'tenphuongxa' => 'Xã Quan Sơn',
      ),
      55 =>
      array(
        'maphuongxa' => 20917056,
        'tenphuongxa' => 'Xã Bằng Mạc',
      ),
      56 =>
      array(
        'maphuongxa' => 20917057,
        'tenphuongxa' => 'Xã Vạn Linh',
      ),
      57 =>
      array(
        'maphuongxa' => 20913058,
        'tenphuongxa' => 'Xã Đồng Đăng',
      ),
      58 =>
      array(
        'maphuongxa' => 20913059,
        'tenphuongxa' => 'Xã Cao Lộc',
      ),
      59 =>
      array(
        'maphuongxa' => 20913060,
        'tenphuongxa' => 'Xã Công Sơn',
      ),
      60 =>
      array(
        'maphuongxa' => 20913061,
        'tenphuongxa' => 'Xã Ba Sơn',
      ),
      61 =>
      array(
        'maphuongxa' => 20901062,
        'tenphuongxa' => 'Phường Tam Thanh',
      ),
      62 =>
      array(
        'maphuongxa' => 20901063,
        'tenphuongxa' => 'Phường Lương Văn Tri',
      ),
      63 =>
      array(
        'maphuongxa' => 20913064,
        'tenphuongxa' => 'Phường Kỳ Lừa',
      ),
      64 =>
      array(
        'maphuongxa' => 20901065,
        'tenphuongxa' => 'Phường Đông Kinh',
      ),
    ),
  ),
  11 =>
  array(
    'matinhBNV' => 12,
    'matinhTMS' => '217',
    'tentinhmoi' => 'Tỉnh Phú Thọ',
    'phuongxa' =>
    array(
      0 =>
      array(
        'maphuongxa' => 21701001,
        'tenphuongxa' => 'Phường Việt Trì',
      ),
      1 =>
      array(
        'maphuongxa' => 21701002,
        'tenphuongxa' => 'Phường Nông Trang',
      ),
      2 =>
      array(
        'maphuongxa' => 21701003,
        'tenphuongxa' => 'Phường Thanh Miếu',
      ),
      3 =>
      array(
        'maphuongxa' => 21701004,
        'tenphuongxa' => 'Phường Vân Phú',
      ),
      4 =>
      array(
        'maphuongxa' => 21701005,
        'tenphuongxa' => 'Xã Hy Cương',
      ),
      5 =>
      array(
        'maphuongxa' => 21721006,
        'tenphuongxa' => 'Xã Lâm Thao',
      ),
      6 =>
      array(
        'maphuongxa' => 21721007,
        'tenphuongxa' => 'Xã Xuân Lũng',
      ),
      7 =>
      array(
        'maphuongxa' => 21721008,
        'tenphuongxa' => 'Xã Phùng Nguyên',
      ),
      8 =>
      array(
        'maphuongxa' => 21721009,
        'tenphuongxa' => 'Xã Bản Nguyên',
      ),
      9 =>
      array(
        'maphuongxa' => 21703010,
        'tenphuongxa' => 'Phường Phong Châu',
      ),
      10 =>
      array(
        'maphuongxa' => 21703011,
        'tenphuongxa' => 'Phường Phú Thọ',
      ),
      11 =>
      array(
        'maphuongxa' => 21703012,
        'tenphuongxa' => 'Phường Âu Cơ',
      ),
      12 =>
      array(
        'maphuongxa' => 21711013,
        'tenphuongxa' => 'Xã Phù Ninh',
      ),
      13 =>
      array(
        'maphuongxa' => 21711014,
        'tenphuongxa' => 'Xã Dân Chủ',
      ),
      14 =>
      array(
        'maphuongxa' => 21711015,
        'tenphuongxa' => 'Xã Phú Mỹ',
      ),
      15 =>
      array(
        'maphuongxa' => 21711016,
        'tenphuongxa' => 'Xã Trạm Thản',
      ),
      16 =>
      array(
        'maphuongxa' => 21711017,
        'tenphuongxa' => 'Xã Bình Phú',
      ),
      17 =>
      array(
        'maphuongxa' => 21709018,
        'tenphuongxa' => 'Xã Thanh Ba',
      ),
      18 =>
      array(
        'maphuongxa' => 21709019,
        'tenphuongxa' => 'Xã Quảng Yên',
      ),
      19 =>
      array(
        'maphuongxa' => 21709020,
        'tenphuongxa' => 'Xã Hoàng Cương',
      ),
      20 =>
      array(
        'maphuongxa' => 21709021,
        'tenphuongxa' => 'Xã Đông Thành',
      ),
      21 =>
      array(
        'maphuongxa' => 21709022,
        'tenphuongxa' => 'Xã Chí Tiên',
      ),
      22 =>
      array(
        'maphuongxa' => 21709023,
        'tenphuongxa' => 'Xã Liên Minh',
      ),
      23 =>
      array(
        'maphuongxa' => 21705024,
        'tenphuongxa' => 'Xã Đoan Hùng',
      ),
      24 =>
      array(
        'maphuongxa' => 21705025,
        'tenphuongxa' => 'Xã Tây Cốc',
      ),
      25 =>
      array(
        'maphuongxa' => 21705026,
        'tenphuongxa' => 'Xã Chân Mộng',
      ),
      26 =>
      array(
        'maphuongxa' => 21705027,
        'tenphuongxa' => 'Xã Chí Đám',
      ),
      27 =>
      array(
        'maphuongxa' => 21705028,
        'tenphuongxa' => 'Xã Bằng Luân',
      ),
      28 =>
      array(
        'maphuongxa' => 21707029,
        'tenphuongxa' => 'Xã Hạ Hòa',
      ),
      29 =>
      array(
        'maphuongxa' => 21707030,
        'tenphuongxa' => 'Xã Đan Thượng',
      ),
      30 =>
      array(
        'maphuongxa' => 21707031,
        'tenphuongxa' => 'Xã Yên Kỳ',
      ),
      31 =>
      array(
        'maphuongxa' => 21707032,
        'tenphuongxa' => 'Xã Vĩnh Chân',
      ),
      32 =>
      array(
        'maphuongxa' => 21707033,
        'tenphuongxa' => 'Xã Văn Lang',
      ),
      33 =>
      array(
        'maphuongxa' => 21707034,
        'tenphuongxa' => 'Xã Hiền Lương',
      ),
      34 =>
      array(
        'maphuongxa' => 21713035,
        'tenphuongxa' => 'Xã Cẩm Khê',
      ),
      35 =>
      array(
        'maphuongxa' => 21713036,
        'tenphuongxa' => 'Xã Phú Khê',
      ),
      36 =>
      array(
        'maphuongxa' => 21713037,
        'tenphuongxa' => 'Xã Hùng Việt',
      ),
      37 =>
      array(
        'maphuongxa' => 21713038,
        'tenphuongxa' => 'Xã Đồng Lương',
      ),
      38 =>
      array(
        'maphuongxa' => 21713039,
        'tenphuongxa' => 'Xã Tiên Lương',
      ),
      39 =>
      array(
        'maphuongxa' => 21713040,
        'tenphuongxa' => 'Xã Vân Bán',
      ),
      40 =>
      array(
        'maphuongxa' => 21717041,
        'tenphuongxa' => 'Xã Tam Nông',
      ),
      41 =>
      array(
        'maphuongxa' => 21717042,
        'tenphuongxa' => 'Xã Thọ Văn',
      ),
      42 =>
      array(
        'maphuongxa' => 21717043,
        'tenphuongxa' => 'Xã Vạn Xuân',
      ),
      43 =>
      array(
        'maphuongxa' => 21717044,
        'tenphuongxa' => 'Xã Hiền Quan',
      ),
      44 =>
      array(
        'maphuongxa' => 21723045,
        'tenphuongxa' => 'Xã Thanh Thuỷ',
      ),
      45 =>
      array(
        'maphuongxa' => 21723046,
        'tenphuongxa' => 'Xã Đào Xá',
      ),
      46 =>
      array(
        'maphuongxa' => 21723047,
        'tenphuongxa' => 'Xã Tu Vũ',
      ),
      47 =>
      array(
        'maphuongxa' => 21719048,
        'tenphuongxa' => 'Xã Thanh Sơn',
      ),
      48 =>
      array(
        'maphuongxa' => 21719049,
        'tenphuongxa' => 'Xã Võ Miếu',
      ),
      49 =>
      array(
        'maphuongxa' => 21719050,
        'tenphuongxa' => 'Xã Văn Miếu',
      ),
      50 =>
      array(
        'maphuongxa' => 21719051,
        'tenphuongxa' => 'Xã Cự Đồng',
      ),
      51 =>
      array(
        'maphuongxa' => 21719052,
        'tenphuongxa' => 'Xã Hương Cần',
      ),
      52 =>
      array(
        'maphuongxa' => 21719053,
        'tenphuongxa' => 'Xã Yên Sơn',
      ),
      53 =>
      array(
        'maphuongxa' => 21719054,
        'tenphuongxa' => 'Xã Khả Cửu',
      ),
      54 =>
      array(
        'maphuongxa' => 21720055,
        'tenphuongxa' => 'Xã Tân Sơn',
      ),
      55 =>
      array(
        'maphuongxa' => 21720056,
        'tenphuongxa' => 'Xã Minh Đài',
      ),
      56 =>
      array(
        'maphuongxa' => 21720057,
        'tenphuongxa' => 'Xã Lai Đồng',
      ),
      57 =>
      array(
        'maphuongxa' => 21720058,
        'tenphuongxa' => 'Xã Thu Cúc',
      ),
      58 =>
      array(
        'maphuongxa' => 21720059,
        'tenphuongxa' => 'Xã Xuân Đài',
      ),
      59 =>
      array(
        'maphuongxa' => 21720060,
        'tenphuongxa' => 'Xã Long Cốc',
      ),
      60 =>
      array(
        'maphuongxa' => 21715061,
        'tenphuongxa' => 'Xã Yên Lập',
      ),
      61 =>
      array(
        'maphuongxa' => 21715062,
        'tenphuongxa' => 'Xã Thượng Long',
      ),
      62 =>
      array(
        'maphuongxa' => 21715063,
        'tenphuongxa' => 'Xã Sơn Lương',
      ),
      63 =>
      array(
        'maphuongxa' => 21715064,
        'tenphuongxa' => 'Xã Xuân Viên',
      ),
      64 =>
      array(
        'maphuongxa' => 21715065,
        'tenphuongxa' => 'Xã Minh Hòa',
      ),
      65 =>
      array(
        'maphuongxa' => 21715066,
        'tenphuongxa' => 'Xã Trung Sơn',
      ),
      66 =>
      array(
        'maphuongxa' => 21915067,
        'tenphuongxa' => 'Xã Tam Sơn',
      ),
      67 =>
      array(
        'maphuongxa' => 21915068,
        'tenphuongxa' => 'Xã Sông Lô',
      ),
      68 =>
      array(
        'maphuongxa' => 21915069,
        'tenphuongxa' => 'Xã Hải Lựu',
      ),
      69 =>
      array(
        'maphuongxa' => 21915070,
        'tenphuongxa' => 'Xã Yên Lãng',
      ),
      70 =>
      array(
        'maphuongxa' => 21903071,
        'tenphuongxa' => 'Xã Lập Thạch',
      ),
      71 =>
      array(
        'maphuongxa' => 21903072,
        'tenphuongxa' => 'Xã Tiên Lữ',
      ),
      72 =>
      array(
        'maphuongxa' => 21903073,
        'tenphuongxa' => 'Xã Thái Hòa',
      ),
      73 =>
      array(
        'maphuongxa' => 21903074,
        'tenphuongxa' => 'Xã Liên Hòa',
      ),
      74 =>
      array(
        'maphuongxa' => 21903075,
        'tenphuongxa' => 'Xã Hợp Lý',
      ),
      75 =>
      array(
        'maphuongxa' => 21903076,
        'tenphuongxa' => 'Xã Sơn Đông',
      ),
      76 =>
      array(
        'maphuongxa' => 21904077,
        'tenphuongxa' => 'Xã Tam Đảo',
      ),
      77 =>
      array(
        'maphuongxa' => 21904078,
        'tenphuongxa' => 'Xã Đại Đình',
      ),
      78 =>
      array(
        'maphuongxa' => 21904079,
        'tenphuongxa' => 'Xã Đạo Trù',
      ),
      79 =>
      array(
        'maphuongxa' => 21905080,
        'tenphuongxa' => 'Xã Tam Dương',
      ),
      80 =>
      array(
        'maphuongxa' => 21905081,
        'tenphuongxa' => 'Xã Hội Thịnh',
      ),
      81 =>
      array(
        'maphuongxa' => 21905082,
        'tenphuongxa' => 'Xã Hoàng An',
      ),
      82 =>
      array(
        'maphuongxa' => 21905083,
        'tenphuongxa' => 'Xã Tam Dương Bắc',
      ),
      83 =>
      array(
        'maphuongxa' => 21907084,
        'tenphuongxa' => 'Xã Vĩnh Tường',
      ),
      84 =>
      array(
        'maphuongxa' => 21907085,
        'tenphuongxa' => 'Xã Thổ Tang',
      ),
      85 =>
      array(
        'maphuongxa' => 21907086,
        'tenphuongxa' => 'Xã Vĩnh Hưng',
      ),
      86 =>
      array(
        'maphuongxa' => 21907087,
        'tenphuongxa' => 'Xã Vĩnh An',
      ),
      87 =>
      array(
        'maphuongxa' => 21907088,
        'tenphuongxa' => 'Xã Vĩnh Phú',
      ),
      88 =>
      array(
        'maphuongxa' => 21907089,
        'tenphuongxa' => 'Xã Vĩnh Thành',
      ),
      89 =>
      array(
        'maphuongxa' => 21909090,
        'tenphuongxa' => 'Xã Yên Lạc',
      ),
      90 =>
      array(
        'maphuongxa' => 21909091,
        'tenphuongxa' => 'Xã Tề Lỗ',
      ),
      91 =>
      array(
        'maphuongxa' => 21909092,
        'tenphuongxa' => 'Xã Liên Châu',
      ),
      92 =>
      array(
        'maphuongxa' => 21909093,
        'tenphuongxa' => 'Xã Tam Hồng',
      ),
      93 =>
      array(
        'maphuongxa' => 21909094,
        'tenphuongxa' => 'Xã Nguyệt Đức',
      ),
      94 =>
      array(
        'maphuongxa' => 21913095,
        'tenphuongxa' => 'Xã Bình Nguyên',
      ),
      95 =>
      array(
        'maphuongxa' => 21913096,
        'tenphuongxa' => 'Xã Xuân Lãng',
      ),
      96 =>
      array(
        'maphuongxa' => 21913097,
        'tenphuongxa' => 'Xã Bình Xuyên',
      ),
      97 =>
      array(
        'maphuongxa' => 21913098,
        'tenphuongxa' => 'Xã Bình Tuyền',
      ),
      98 =>
      array(
        'maphuongxa' => 21901099,
        'tenphuongxa' => 'Phường Vĩnh Phúc',
      ),
      99 =>
      array(
        'maphuongxa' => 21901100,
        'tenphuongxa' => 'Phường Vĩnh Yên',
      ),
      100 =>
      array(
        'maphuongxa' => 21902101,
        'tenphuongxa' => 'Phường Phúc Yên',
      ),
      101 =>
      array(
        'maphuongxa' => 21902102,
        'tenphuongxa' => 'Phường Xuân Hòa',
      ),
      102 =>
      array(
        'maphuongxa' => 30510103,
        'tenphuongxa' => 'Xã Cao Phong',
      ),
      103 =>
      array(
        'maphuongxa' => 30510104,
        'tenphuongxa' => 'Xã Mường Thàng',
      ),
      104 =>
      array(
        'maphuongxa' => 30510105,
        'tenphuongxa' => 'Xã Thung Nai',
      ),
      105 =>
      array(
        'maphuongxa' => 30503106,
        'tenphuongxa' => 'Xã Đà Bắc',
      ),
      106 =>
      array(
        'maphuongxa' => 30503107,
        'tenphuongxa' => 'Xã Cao Sơn',
      ),
      107 =>
      array(
        'maphuongxa' => 30503108,
        'tenphuongxa' => 'Xã Đức Nhàn',
      ),
      108 =>
      array(
        'maphuongxa' => 30503109,
        'tenphuongxa' => 'Xã Quy Đức',
      ),
      109 =>
      array(
        'maphuongxa' => 30503110,
        'tenphuongxa' => 'Xã Tân Pheo',
      ),
      110 =>
      array(
        'maphuongxa' => 30503111,
        'tenphuongxa' => 'Xã Tiền Phong',
      ),
      111 =>
      array(
        'maphuongxa' => 30511112,
        'tenphuongxa' => 'Xã Kim Bôi',
      ),
      112 =>
      array(
        'maphuongxa' => 30511113,
        'tenphuongxa' => 'Xã Mường Động',
      ),
      113 =>
      array(
        'maphuongxa' => 30511114,
        'tenphuongxa' => 'Xã Dũng Tiến',
      ),
      114 =>
      array(
        'maphuongxa' => 30511115,
        'tenphuongxa' => 'Xã Hợp Kim',
      ),
      115 =>
      array(
        'maphuongxa' => 30511116,
        'tenphuongxa' => 'Xã Nật Sơn',
      ),
      116 =>
      array(
        'maphuongxa' => 30515117,
        'tenphuongxa' => 'Xã Lạc Sơn',
      ),
      117 =>
      array(
        'maphuongxa' => 30515118,
        'tenphuongxa' => 'Xã Mường Vang',
      ),
      118 =>
      array(
        'maphuongxa' => 30515119,
        'tenphuongxa' => 'Xã Đại Đồng',
      ),
      119 =>
      array(
        'maphuongxa' => 30515120,
        'tenphuongxa' => 'Xã Ngọc Sơn',
      ),
      120 =>
      array(
        'maphuongxa' => 30515121,
        'tenphuongxa' => 'Xã Nhân Nghĩa',
      ),
      121 =>
      array(
        'maphuongxa' => 30515122,
        'tenphuongxa' => 'Xã Quyết Thắng',
      ),
      122 =>
      array(
        'maphuongxa' => 30515123,
        'tenphuongxa' => 'Xã Thượng Cốc',
      ),
      123 =>
      array(
        'maphuongxa' => 30515124,
        'tenphuongxa' => 'Xã Yên Phú',
      ),
      124 =>
      array(
        'maphuongxa' => 30517125,
        'tenphuongxa' => 'Xã Lạc Thủy',
      ),
      125 =>
      array(
        'maphuongxa' => 30517126,
        'tenphuongxa' => 'Xã An Bình',
      ),
      126 =>
      array(
        'maphuongxa' => 30517127,
        'tenphuongxa' => 'Xã An Nghĩa',
      ),
      127 =>
      array(
        'maphuongxa' => 30509128,
        'tenphuongxa' => 'Xã Lương Sơn',
      ),
      128 =>
      array(
        'maphuongxa' => 30509129,
        'tenphuongxa' => 'Xã Cao Dương',
      ),
      129 =>
      array(
        'maphuongxa' => 30509130,
        'tenphuongxa' => 'Xã Liên Sơn',
      ),
      130 =>
      array(
        'maphuongxa' => 30505131,
        'tenphuongxa' => 'Xã Mai Châu',
      ),
      131 =>
      array(
        'maphuongxa' => 30505132,
        'tenphuongxa' => 'Xã Bao La',
      ),
      132 =>
      array(
        'maphuongxa' => 30505133,
        'tenphuongxa' => 'Xã Mai Hạ',
      ),
      133 =>
      array(
        'maphuongxa' => 30505134,
        'tenphuongxa' => 'Xã Pà Cò',
      ),
      134 =>
      array(
        'maphuongxa' => 30505135,
        'tenphuongxa' => 'Xã Tân Mai',
      ),
      135 =>
      array(
        'maphuongxa' => 30513136,
        'tenphuongxa' => 'Xã Tân Lạc',
      ),
      136 =>
      array(
        'maphuongxa' => 30513137,
        'tenphuongxa' => 'Xã Mường Bi',
      ),
      137 =>
      array(
        'maphuongxa' => 30513138,
        'tenphuongxa' => 'Xã Mường Hoa',
      ),
      138 =>
      array(
        'maphuongxa' => 30513139,
        'tenphuongxa' => 'Xã Toàn Thắng',
      ),
      139 =>
      array(
        'maphuongxa' => 30513140,
        'tenphuongxa' => 'Xã Vân Sơn',
      ),
      140 =>
      array(
        'maphuongxa' => 30519141,
        'tenphuongxa' => 'Xã Yên Thủy',
      ),
      141 =>
      array(
        'maphuongxa' => 30519142,
        'tenphuongxa' => 'Xã Lạc Lương',
      ),
      142 =>
      array(
        'maphuongxa' => 30519143,
        'tenphuongxa' => 'Xã Yên Trị',
      ),
      143 =>
      array(
        'maphuongxa' => 30501144,
        'tenphuongxa' => 'Xã Thịnh Minh',
      ),
      144 =>
      array(
        'maphuongxa' => 30501145,
        'tenphuongxa' => 'Phường Hoà Bình',
      ),
      145 =>
      array(
        'maphuongxa' => 30501146,
        'tenphuongxa' => 'Phường Kỳ Sơn',
      ),
      146 =>
      array(
        'maphuongxa' => 30501147,
        'tenphuongxa' => 'Phường Tân Hoà',
      ),
      147 =>
      array(
        'maphuongxa' => 30501148,
        'tenphuongxa' => 'Phường Thống Nhất',
      ),
    ),
  ),
  12 =>
  array(
    'matinhBNV' => 13,
    'matinhTMS' => '301',
    'tentinhmoi' => 'Tỉnh Điện Biên',
    'phuongxa' =>
    array(
      0 =>
      array(
        'maphuongxa' => 30101001,
        'tenphuongxa' => 'Xã Mường Phăng',
      ),
      1 =>
      array(
        'maphuongxa' => 30101002,
        'tenphuongxa' => 'Phường Điện Biên Phủ',
      ),
      2 =>
      array(
        'maphuongxa' => 30101003,
        'tenphuongxa' => 'Phường Mường Thanh',
      ),
      3 =>
      array(
        'maphuongxa' => 30103004,
        'tenphuongxa' => 'Phường Mường Lay',
      ),
      4 =>
      array(
        'maphuongxa' => 30117005,
        'tenphuongxa' => 'Xã Thanh Nưa',
      ),
      5 =>
      array(
        'maphuongxa' => 30117006,
        'tenphuongxa' => 'Xã Thanh An',
      ),
      6 =>
      array(
        'maphuongxa' => 30117007,
        'tenphuongxa' => 'Xã Thanh Yên',
      ),
      7 =>
      array(
        'maphuongxa' => 30117008,
        'tenphuongxa' => 'Xã Sam Mứn',
      ),
      8 =>
      array(
        'maphuongxa' => 30117009,
        'tenphuongxa' => 'Xã Núa Ngam',
      ),
      9 =>
      array(
        'maphuongxa' => 30117010,
        'tenphuongxa' => 'Xã Mường Nhà',
      ),
      10 =>
      array(
        'maphuongxa' => 30115011,
        'tenphuongxa' => 'Xã Tuần Giáo',
      ),
      11 =>
      array(
        'maphuongxa' => 30115012,
        'tenphuongxa' => 'Xã Quài Tở',
      ),
      12 =>
      array(
        'maphuongxa' => 30115013,
        'tenphuongxa' => 'Xã Mường Mùn',
      ),
      13 =>
      array(
        'maphuongxa' => 30115014,
        'tenphuongxa' => 'Xã Pú Nhung',
      ),
      14 =>
      array(
        'maphuongxa' => 30115015,
        'tenphuongxa' => 'Xã Chiềng Sinh',
      ),
      15 =>
      array(
        'maphuongxa' => 30113016,
        'tenphuongxa' => 'Xã Tủa Chùa',
      ),
      16 =>
      array(
        'maphuongxa' => 30113017,
        'tenphuongxa' => 'Xã Sín Chải',
      ),
      17 =>
      array(
        'maphuongxa' => 30113018,
        'tenphuongxa' => 'Xã Sính Phình',
      ),
      18 =>
      array(
        'maphuongxa' => 30113019,
        'tenphuongxa' => 'Xã Tủa Thàng',
      ),
      19 =>
      array(
        'maphuongxa' => 30113020,
        'tenphuongxa' => 'Xã Sáng Nhè',
      ),
      20 =>
      array(
        'maphuongxa' => 30111021,
        'tenphuongxa' => 'Xã Na Sang',
      ),
      21 =>
      array(
        'maphuongxa' => 30111022,
        'tenphuongxa' => 'Xã Mường Tùng',
      ),
      22 =>
      array(
        'maphuongxa' => 30111023,
        'tenphuongxa' => 'Xã Pa Ham',
      ),
      23 =>
      array(
        'maphuongxa' => 30111024,
        'tenphuongxa' => 'Xã Nậm Nèn',
      ),
      24 =>
      array(
        'maphuongxa' => 30111025,
        'tenphuongxa' => 'Xã Mường Pồn',
      ),
      25 =>
      array(
        'maphuongxa' => 30119026,
        'tenphuongxa' => 'Xã Na Son',
      ),
      26 =>
      array(
        'maphuongxa' => 30119027,
        'tenphuongxa' => 'Xã Xa Dung',
      ),
      27 =>
      array(
        'maphuongxa' => 30119028,
        'tenphuongxa' => 'Xã Pu Nhi',
      ),
      28 =>
      array(
        'maphuongxa' => 30119029,
        'tenphuongxa' => 'Xã Mường Luân',
      ),
      29 =>
      array(
        'maphuongxa' => 30119030,
        'tenphuongxa' => 'Xã Tìa Dình',
      ),
      30 =>
      array(
        'maphuongxa' => 30119031,
        'tenphuongxa' => 'Xã Phình Giàng',
      ),
      31 =>
      array(
        'maphuongxa' => 30123032,
        'tenphuongxa' => 'Xã Mường Chà',
      ),
      32 =>
      array(
        'maphuongxa' => 30123033,
        'tenphuongxa' => 'Xã Nà Hỳ',
      ),
      33 =>
      array(
        'maphuongxa' => 30123034,
        'tenphuongxa' => 'Xã Nà Bủng',
      ),
      34 =>
      array(
        'maphuongxa' => 30123035,
        'tenphuongxa' => 'Xã Chà Tở',
      ),
      35 =>
      array(
        'maphuongxa' => 30123036,
        'tenphuongxa' => 'Xã Si Pa Phìn',
      ),
      36 =>
      array(
        'maphuongxa' => 30104037,
        'tenphuongxa' => 'Xã Mường Nhé',
      ),
      37 =>
      array(
        'maphuongxa' => 30104038,
        'tenphuongxa' => 'Xã Sín Thầu',
      ),
      38 =>
      array(
        'maphuongxa' => 30104039,
        'tenphuongxa' => 'Xã Mường Toong',
      ),
      39 =>
      array(
        'maphuongxa' => 30104040,
        'tenphuongxa' => 'Xã Nậm Kè',
      ),
      40 =>
      array(
        'maphuongxa' => 30104041,
        'tenphuongxa' => 'Xã Quảng Lâm',
      ),
      41 =>
      array(
        'maphuongxa' => 30121042,
        'tenphuongxa' => 'Xã Mường Ảng',
      ),
      42 =>
      array(
        'maphuongxa' => 30121043,
        'tenphuongxa' => 'Xã Nà Tấu',
      ),
      43 =>
      array(
        'maphuongxa' => 30121044,
        'tenphuongxa' => 'Xã Búng Lao',
      ),
      44 =>
      array(
        'maphuongxa' => 30121045,
        'tenphuongxa' => 'Xã Mường Lạn',
      ),
    ),
  ),
  13 =>
  array(
    'matinhBNV' => 14,
    'matinhTMS' => '302',
    'tentinhmoi' => 'Tỉnh Lai Châu',
    'phuongxa' =>
    array(
      0 =>
      array(
        'maphuongxa' => 30209001,
        'tenphuongxa' => 'Xã Mường Kim',
      ),
      1 =>
      array(
        'maphuongxa' => 30209002,
        'tenphuongxa' => 'Xã Khoen On',
      ),
      2 =>
      array(
        'maphuongxa' => 30209003,
        'tenphuongxa' => 'Xã Than Uyên',
      ),
      3 =>
      array(
        'maphuongxa' => 30209004,
        'tenphuongxa' => 'Xã Mường Than',
      ),
      4 =>
      array(
        'maphuongxa' => 30211005,
        'tenphuongxa' => 'Xã Pắc Ta',
      ),
      5 =>
      array(
        'maphuongxa' => 30211006,
        'tenphuongxa' => 'Xã Nậm Sỏ',
      ),
      6 =>
      array(
        'maphuongxa' => 30211007,
        'tenphuongxa' => 'Xã Tân Uyên',
      ),
      7 =>
      array(
        'maphuongxa' => 30211008,
        'tenphuongxa' => 'Xã Mường Khoa',
      ),
      8 =>
      array(
        'maphuongxa' => 30205009,
        'tenphuongxa' => 'Xã Bản Bo',
      ),
      9 =>
      array(
        'maphuongxa' => 30205010,
        'tenphuongxa' => 'Xã Bình Lư',
      ),
      10 =>
      array(
        'maphuongxa' => 30205011,
        'tenphuongxa' => 'Xã Tả Lèng',
      ),
      11 =>
      array(
        'maphuongxa' => 30205012,
        'tenphuongxa' => 'Xã Khun Há',
      ),
      12 =>
      array(
        'maphuongxa' => 30202013,
        'tenphuongxa' => 'Phường Tân Phong',
      ),
      13 =>
      array(
        'maphuongxa' => 30202014,
        'tenphuongxa' => 'Phường Đoàn Kết',
      ),
      14 =>
      array(
        'maphuongxa' => 30203015,
        'tenphuongxa' => 'Xã Sin Suối Hồ',
      ),
      15 =>
      array(
        'maphuongxa' => 30203016,
        'tenphuongxa' => 'Xã Phong Thổ',
      ),
      16 =>
      array(
        'maphuongxa' => 30203017,
        'tenphuongxa' => 'Xã Sì Lở Lầu',
      ),
      17 =>
      array(
        'maphuongxa' => 30203018,
        'tenphuongxa' => 'Xã Dào San',
      ),
      18 =>
      array(
        'maphuongxa' => 30203019,
        'tenphuongxa' => 'Xã Khổng Lào',
      ),
      19 =>
      array(
        'maphuongxa' => 30207020,
        'tenphuongxa' => 'Xã Tủa Sín Chải',
      ),
      20 =>
      array(
        'maphuongxa' => 30207021,
        'tenphuongxa' => 'Xã Sìn Hồ',
      ),
      21 =>
      array(
        'maphuongxa' => 30207022,
        'tenphuongxa' => 'Xã Hồng Thu',
      ),
      22 =>
      array(
        'maphuongxa' => 30207023,
        'tenphuongxa' => 'Xã Nậm Tăm',
      ),
      23 =>
      array(
        'maphuongxa' => 30207024,
        'tenphuongxa' => 'Xã Pu Sam Cáp',
      ),
      24 =>
      array(
        'maphuongxa' => 30207025,
        'tenphuongxa' => 'Xã Nậm Cuổi',
      ),
      25 =>
      array(
        'maphuongxa' => 30207026,
        'tenphuongxa' => 'Xã Nậm Mạ',
      ),
      26 =>
      array(
        'maphuongxa' => 30213027,
        'tenphuongxa' => 'Xã Lê Lợi',
      ),
      27 =>
      array(
        'maphuongxa' => 30213028,
        'tenphuongxa' => 'Xã Nậm Hàng',
      ),
      28 =>
      array(
        'maphuongxa' => 30213029,
        'tenphuongxa' => 'Xã Mường Mô',
      ),
      29 =>
      array(
        'maphuongxa' => 30213030,
        'tenphuongxa' => 'Xã Hua Bum',
      ),
      30 =>
      array(
        'maphuongxa' => 30213031,
        'tenphuongxa' => 'Xã Pa Tần',
      ),
      31 =>
      array(
        'maphuongxa' => 30201032,
        'tenphuongxa' => 'Xã Bum Nưa',
      ),
      32 =>
      array(
        'maphuongxa' => 30201033,
        'tenphuongxa' => 'Xã Bum Tở',
      ),
      33 =>
      array(
        'maphuongxa' => 30201034,
        'tenphuongxa' => 'Xã Mường Tè',
      ),
      34 =>
      array(
        'maphuongxa' => 30201035,
        'tenphuongxa' => 'Xã Thu Lũm',
      ),
      35 =>
      array(
        'maphuongxa' => 30201036,
        'tenphuongxa' => 'Xã Pa Ủ',
      ),
      36 =>
      array(
        'maphuongxa' => 30201037,
        'tenphuongxa' => 'Xã Tà Tổng',
      ),
      37 =>
      array(
        'maphuongxa' => 30201038,
        'tenphuongxa' => 'Xã Mù Cả',
      ),
    ),
  ),
  14 =>
  array(
    'matinhBNV' => 15,
    'matinhTMS' => '303',
    'tentinhmoi' => 'Tỉnh Sơn La',
    'phuongxa' =>
    array(
      0 =>
      array(
        'maphuongxa' => 30301001,
        'tenphuongxa' => 'Phường Tô Hiệu',
      ),
      1 =>
      array(
        'maphuongxa' => 30301002,
        'tenphuongxa' => 'Phường Chiềng An',
      ),
      2 =>
      array(
        'maphuongxa' => 30301003,
        'tenphuongxa' => 'Phường Chiềng Cơi',
      ),
      3 =>
      array(
        'maphuongxa' => 30301004,
        'tenphuongxa' => 'Phường Chiềng Sinh',
      ),
      4 =>
      array(
        'maphuongxa' => 30319005,
        'tenphuongxa' => 'Phường Mộc Châu',
      ),
      5 =>
      array(
        'maphuongxa' => 30319006,
        'tenphuongxa' => 'Phường Mộc Sơn',
      ),
      6 =>
      array(
        'maphuongxa' => 30319007,
        'tenphuongxa' => 'Phường Vân Sơn',
      ),
      7 =>
      array(
        'maphuongxa' => 30319008,
        'tenphuongxa' => 'Phường Thảo Nguyên',
      ),
      8 =>
      array(
        'maphuongxa' => 30319009,
        'tenphuongxa' => 'Xã Đoàn Kết',
      ),
      9 =>
      array(
        'maphuongxa' => 30319010,
        'tenphuongxa' => 'Xã Lóng Sập',
      ),
      10 =>
      array(
        'maphuongxa' => 30319011,
        'tenphuongxa' => 'Xã Chiềng Sơn',
      ),
      11 =>
      array(
        'maphuongxa' => 30323012,
        'tenphuongxa' => 'Xã Vân Hồ',
      ),
      12 =>
      array(
        'maphuongxa' => 30323013,
        'tenphuongxa' => 'Xã Song Khủa',
      ),
      13 =>
      array(
        'maphuongxa' => 30323014,
        'tenphuongxa' => 'Xã Tô Múa',
      ),
      14 =>
      array(
        'maphuongxa' => 30323015,
        'tenphuongxa' => 'Xã Xuân Nha',
      ),
      15 =>
      array(
        'maphuongxa' => 30303016,
        'tenphuongxa' => 'Xã Quỳnh Nhai',
      ),
      16 =>
      array(
        'maphuongxa' => 30303017,
        'tenphuongxa' => 'Xã Mường Chiên',
      ),
      17 =>
      array(
        'maphuongxa' => 30303018,
        'tenphuongxa' => 'Xã Mường Giôn',
      ),
      18 =>
      array(
        'maphuongxa' => 30303019,
        'tenphuongxa' => 'Xã Mường Sại',
      ),
      19 =>
      array(
        'maphuongxa' => 30307020,
        'tenphuongxa' => 'Xã Thuận Châu',
      ),
      20 =>
      array(
        'maphuongxa' => 30307021,
        'tenphuongxa' => 'Xã Chiềng La',
      ),
      21 =>
      array(
        'maphuongxa' => 30307022,
        'tenphuongxa' => 'Xã Nậm Lầu',
      ),
      22 =>
      array(
        'maphuongxa' => 30307023,
        'tenphuongxa' => 'Xã Muổi Nọi',
      ),
      23 =>
      array(
        'maphuongxa' => 30307024,
        'tenphuongxa' => 'Xã Mường Khiêng',
      ),
      24 =>
      array(
        'maphuongxa' => 30307025,
        'tenphuongxa' => 'Xã Co Mạ',
      ),
      25 =>
      array(
        'maphuongxa' => 30307026,
        'tenphuongxa' => 'Xã Bình Thuận',
      ),
      26 =>
      array(
        'maphuongxa' => 30307027,
        'tenphuongxa' => 'Xã Mường É',
      ),
      27 =>
      array(
        'maphuongxa' => 30307028,
        'tenphuongxa' => 'Xã Long Hẹ',
      ),
      28 =>
      array(
        'maphuongxa' => 30305029,
        'tenphuongxa' => 'Xã Mường La',
      ),
      29 =>
      array(
        'maphuongxa' => 30305030,
        'tenphuongxa' => 'Xã Chiềng Lao',
      ),
      30 =>
      array(
        'maphuongxa' => 30305031,
        'tenphuongxa' => 'Xã Mường Bú',
      ),
      31 =>
      array(
        'maphuongxa' => 30305032,
        'tenphuongxa' => 'Xã Chiềng Hoa',
      ),
      32 =>
      array(
        'maphuongxa' => 30309033,
        'tenphuongxa' => 'Xã Bắc Yên',
      ),
      33 =>
      array(
        'maphuongxa' => 30309034,
        'tenphuongxa' => 'Xã Tà Xùa',
      ),
      34 =>
      array(
        'maphuongxa' => 30309035,
        'tenphuongxa' => 'Xã Tạ Khoa',
      ),
      35 =>
      array(
        'maphuongxa' => 30309036,
        'tenphuongxa' => 'Xã Xím Vàng',
      ),
      36 =>
      array(
        'maphuongxa' => 30309037,
        'tenphuongxa' => 'Xã Pắc Ngà',
      ),
      37 =>
      array(
        'maphuongxa' => 30309038,
        'tenphuongxa' => 'Xã Chiềng Sại',
      ),
      38 =>
      array(
        'maphuongxa' => 30311039,
        'tenphuongxa' => 'Xã Phù Yên',
      ),
      39 =>
      array(
        'maphuongxa' => 30311040,
        'tenphuongxa' => 'Xã Gia Phù',
      ),
      40 =>
      array(
        'maphuongxa' => 30311041,
        'tenphuongxa' => 'Xã Tường Hạ',
      ),
      41 =>
      array(
        'maphuongxa' => 30311042,
        'tenphuongxa' => 'Xã Mường Cơi',
      ),
      42 =>
      array(
        'maphuongxa' => 30311043,
        'tenphuongxa' => 'Xã Mường Bang',
      ),
      43 =>
      array(
        'maphuongxa' => 30311044,
        'tenphuongxa' => 'Xã Tân Phong',
      ),
      44 =>
      array(
        'maphuongxa' => 30311045,
        'tenphuongxa' => 'Xã Kim Bon',
      ),
      45 =>
      array(
        'maphuongxa' => 30317046,
        'tenphuongxa' => 'Xã Yên Châu',
      ),
      46 =>
      array(
        'maphuongxa' => 30317047,
        'tenphuongxa' => 'Xã Chiềng Hặc',
      ),
      47 =>
      array(
        'maphuongxa' => 30317048,
        'tenphuongxa' => 'Xã Lóng Phiêng',
      ),
      48 =>
      array(
        'maphuongxa' => 30317049,
        'tenphuongxa' => 'Xã Yên Sơn',
      ),
      49 =>
      array(
        'maphuongxa' => 30313050,
        'tenphuongxa' => 'Xã Chiềng Mai',
      ),
      50 =>
      array(
        'maphuongxa' => 30313051,
        'tenphuongxa' => 'Xã Mai Sơn',
      ),
      51 =>
      array(
        'maphuongxa' => 30313052,
        'tenphuongxa' => 'Xã Phiêng Pằn',
      ),
      52 =>
      array(
        'maphuongxa' => 30313053,
        'tenphuongxa' => 'Xã Chiềng Mung',
      ),
      53 =>
      array(
        'maphuongxa' => 30313054,
        'tenphuongxa' => 'Xã Phiêng Cằm',
      ),
      54 =>
      array(
        'maphuongxa' => 30313055,
        'tenphuongxa' => 'Xã Mường Chanh',
      ),
      55 =>
      array(
        'maphuongxa' => 30313056,
        'tenphuongxa' => 'Xã Tà Hộc',
      ),
      56 =>
      array(
        'maphuongxa' => 30313057,
        'tenphuongxa' => 'Xã Chiềng Sung',
      ),
      57 =>
      array(
        'maphuongxa' => 30315058,
        'tenphuongxa' => 'Xã Bó Sinh',
      ),
      58 =>
      array(
        'maphuongxa' => 30315059,
        'tenphuongxa' => 'Xã Chiềng Khương',
      ),
      59 =>
      array(
        'maphuongxa' => 30315060,
        'tenphuongxa' => 'Xã Mường Hung',
      ),
      60 =>
      array(
        'maphuongxa' => 30315061,
        'tenphuongxa' => 'Xã Chiềng Khoong',
      ),
      61 =>
      array(
        'maphuongxa' => 30315062,
        'tenphuongxa' => 'Xã Mường Lầm',
      ),
      62 =>
      array(
        'maphuongxa' => 30315063,
        'tenphuongxa' => 'Xã Nậm Ty',
      ),
      63 =>
      array(
        'maphuongxa' => 30315064,
        'tenphuongxa' => 'Xã Sông Mã',
      ),
      64 =>
      array(
        'maphuongxa' => 30315065,
        'tenphuongxa' => 'Xã Huổi Một',
      ),
      65 =>
      array(
        'maphuongxa' => 30315066,
        'tenphuongxa' => 'Xã Chiềng Sơ',
      ),
      66 =>
      array(
        'maphuongxa' => 30321067,
        'tenphuongxa' => 'Xã Sốp Cộp',
      ),
      67 =>
      array(
        'maphuongxa' => 30321068,
        'tenphuongxa' => 'Xã Púng Bánh',
      ),
      68 =>
      array(
        'maphuongxa' => 30319069,
        'tenphuongxa' => 'Xã Tân Yên',
      ),
      69 =>
      array(
        'maphuongxa' => 30307070,
        'tenphuongxa' => 'Xã Mường Bám',
      ),
      70 =>
      array(
        'maphuongxa' => 30305071,
        'tenphuongxa' => 'Xã Ngọc Chiến',
      ),
      71 =>
      array(
        'maphuongxa' => 30311072,
        'tenphuongxa' => 'Xã Suối Tọ',
      ),
      72 =>
      array(
        'maphuongxa' => 30317073,
        'tenphuongxa' => 'Xã Phiêng Khoài',
      ),
      73 =>
      array(
        'maphuongxa' => 30321074,
        'tenphuongxa' => 'Xã Mường Lạn',
      ),
      74 =>
      array(
        'maphuongxa' => 30321075,
        'tenphuongxa' => 'Xã Mường Lèo',
      ),
    ),
  ),
  15 =>
  array(
    'matinhBNV' => 16,
    'matinhTMS' => '401',
    'tentinhmoi' => 'Tỉnh Thanh Hóa',
    'phuongxa' =>
    array(
      0 =>
      array(
        'maphuongxa' => 40101001,
        'tenphuongxa' => 'Phường Hạc Thành',
      ),
      1 =>
      array(
        'maphuongxa' => 40101002,
        'tenphuongxa' => 'Phường Quảng Phú',
      ),
      2 =>
      array(
        'maphuongxa' => 40101003,
        'tenphuongxa' => 'Phường Đông Quang',
      ),
      3 =>
      array(
        'maphuongxa' => 40101004,
        'tenphuongxa' => 'Phường Đông Sơn',
      ),
      4 =>
      array(
        'maphuongxa' => 40101005,
        'tenphuongxa' => 'Phường Đông Tiến',
      ),
      5 =>
      array(
        'maphuongxa' => 40101006,
        'tenphuongxa' => 'Phường Hàm Rồng',
      ),
      6 =>
      array(
        'maphuongxa' => 40101007,
        'tenphuongxa' => 'Phường Nguyệt Viên',
      ),
      7 =>
      array(
        'maphuongxa' => 40105008,
        'tenphuongxa' => 'Phường Sầm Sơn',
      ),
      8 =>
      array(
        'maphuongxa' => 40105009,
        'tenphuongxa' => 'Phường Nam Sầm Sơn',
      ),
      9 =>
      array(
        'maphuongxa' => 40103010,
        'tenphuongxa' => 'Phường Bỉm Sơn',
      ),
      10 =>
      array(
        'maphuongxa' => 40103011,
        'tenphuongxa' => 'Phường Quang Trung',
      ),
      11 =>
      array(
        'maphuongxa' => 40153012,
        'tenphuongxa' => 'Phường Ngọc Sơn',
      ),
      12 =>
      array(
        'maphuongxa' => 40153013,
        'tenphuongxa' => 'Phường Tân Dân',
      ),
      13 =>
      array(
        'maphuongxa' => 40153014,
        'tenphuongxa' => 'Phường Hải Lĩnh',
      ),
      14 =>
      array(
        'maphuongxa' => 40153015,
        'tenphuongxa' => 'Phường Tĩnh Gia',
      ),
      15 =>
      array(
        'maphuongxa' => 40153016,
        'tenphuongxa' => 'Phường Đào Duy Tư',
      ),
      16 =>
      array(
        'maphuongxa' => 40153017,
        'tenphuongxa' => 'Phường Hải Bình',
      ),
      17 =>
      array(
        'maphuongxa' => 40153018,
        'tenphuongxa' => 'Phường Trúc Lâm',
      ),
      18 =>
      array(
        'maphuongxa' => 40153019,
        'tenphuongxa' => 'Phường Nghi Sơn',
      ),
      19 =>
      array(
        'maphuongxa' => 40153020,
        'tenphuongxa' => 'Xã Các Sơn',
      ),
      20 =>
      array(
        'maphuongxa' => 40153021,
        'tenphuongxa' => 'Xã Trường Lâm',
      ),
      21 =>
      array(
        'maphuongxa' => 40131022,
        'tenphuongxa' => 'Xã Hà Trung',
      ),
      22 =>
      array(
        'maphuongxa' => 40131023,
        'tenphuongxa' => 'Xã Tống Sơn',
      ),
      23 =>
      array(
        'maphuongxa' => 40131024,
        'tenphuongxa' => 'Xã Hà Long',
      ),
      24 =>
      array(
        'maphuongxa' => 40131025,
        'tenphuongxa' => 'Xã Hoạt Giang',
      ),
      25 =>
      array(
        'maphuongxa' => 40131026,
        'tenphuongxa' => 'Xã Lĩnh Toại',
      ),
      26 =>
      array(
        'maphuongxa' => 40139027,
        'tenphuongxa' => 'Xã Triệu Lộc',
      ),
      27 =>
      array(
        'maphuongxa' => 40139028,
        'tenphuongxa' => 'Xã Đông Thành',
      ),
      28 =>
      array(
        'maphuongxa' => 40139029,
        'tenphuongxa' => 'Xã Hậu Lộc',
      ),
      29 =>
      array(
        'maphuongxa' => 40139030,
        'tenphuongxa' => 'Xã Hoa Lộc',
      ),
      30 =>
      array(
        'maphuongxa' => 40139031,
        'tenphuongxa' => 'Xã Vạn Lộc',
      ),
      31 =>
      array(
        'maphuongxa' => 40133032,
        'tenphuongxa' => 'Xã Nga Sơn',
      ),
      32 =>
      array(
        'maphuongxa' => 40133033,
        'tenphuongxa' => 'Xã Nga Thắng',
      ),
      33 =>
      array(
        'maphuongxa' => 40133034,
        'tenphuongxa' => 'Xã Hồ Vương',
      ),
      34 =>
      array(
        'maphuongxa' => 40133035,
        'tenphuongxa' => 'Xã Tân Tiến',
      ),
      35 =>
      array(
        'maphuongxa' => 40133036,
        'tenphuongxa' => 'Xã Nga An',
      ),
      36 =>
      array(
        'maphuongxa' => 40133037,
        'tenphuongxa' => 'Xã Ba Đình',
      ),
      37 =>
      array(
        'maphuongxa' => 40143038,
        'tenphuongxa' => 'Xã Hoằng Hóa',
      ),
      38 =>
      array(
        'maphuongxa' => 40143039,
        'tenphuongxa' => 'Xã Hoằng Tiến',
      ),
      39 =>
      array(
        'maphuongxa' => 40143040,
        'tenphuongxa' => 'Xã Hoằng Thanh',
      ),
      40 =>
      array(
        'maphuongxa' => 40143041,
        'tenphuongxa' => 'Xã Hoằng Lộc',
      ),
      41 =>
      array(
        'maphuongxa' => 40143042,
        'tenphuongxa' => 'Xã Hoằng Châu',
      ),
      42 =>
      array(
        'maphuongxa' => 40143043,
        'tenphuongxa' => 'Xã Hoằng Sơn',
      ),
      43 =>
      array(
        'maphuongxa' => 40143044,
        'tenphuongxa' => 'Xã Hoằng Phú',
      ),
      44 =>
      array(
        'maphuongxa' => 40143045,
        'tenphuongxa' => 'Xã Hoằng Giang',
      ),
      45 =>
      array(
        'maphuongxa' => 40149046,
        'tenphuongxa' => 'Xã Lưu Vệ',
      ),
      46 =>
      array(
        'maphuongxa' => 40149047,
        'tenphuongxa' => 'Xã Quảng Yên',
      ),
      47 =>
      array(
        'maphuongxa' => 40149048,
        'tenphuongxa' => 'Xã Quảng Ngọc',
      ),
      48 =>
      array(
        'maphuongxa' => 40149049,
        'tenphuongxa' => 'Xã Quảng Ninh',
      ),
      49 =>
      array(
        'maphuongxa' => 40149050,
        'tenphuongxa' => 'Xã Quảng Bình',
      ),
      50 =>
      array(
        'maphuongxa' => 40149051,
        'tenphuongxa' => 'Xã Tiên Trang',
      ),
      51 =>
      array(
        'maphuongxa' => 40149052,
        'tenphuongxa' => 'Xã Quảng Chính',
      ),
      52 =>
      array(
        'maphuongxa' => 40151053,
        'tenphuongxa' => 'Xã Nông Cống',
      ),
      53 =>
      array(
        'maphuongxa' => 40151054,
        'tenphuongxa' => 'Xã Thắng Lợi',
      ),
      54 =>
      array(
        'maphuongxa' => 40151055,
        'tenphuongxa' => 'Xã Trung Chính',
      ),
      55 =>
      array(
        'maphuongxa' => 40151056,
        'tenphuongxa' => 'Xã Trường Văn',
      ),
      56 =>
      array(
        'maphuongxa' => 40151057,
        'tenphuongxa' => 'Xã Thăng Bình',
      ),
      57 =>
      array(
        'maphuongxa' => 40151058,
        'tenphuongxa' => 'Xã Tượng Lĩnh',
      ),
      58 =>
      array(
        'maphuongxa' => 40151059,
        'tenphuongxa' => 'Xã Công Chính',
      ),
      59 =>
      array(
        'maphuongxa' => 40141060,
        'tenphuongxa' => 'Xã Thiệu Hóa',
      ),
      60 =>
      array(
        'maphuongxa' => 40141061,
        'tenphuongxa' => 'Xã Thiệu Quang',
      ),
      61 =>
      array(
        'maphuongxa' => 40141062,
        'tenphuongxa' => 'Xã Thiệu Tiến',
      ),
      62 =>
      array(
        'maphuongxa' => 40141063,
        'tenphuongxa' => 'Xã Thiệu Toán',
      ),
      63 =>
      array(
        'maphuongxa' => 40141064,
        'tenphuongxa' => 'Xã Thiệu Trung',
      ),
      64 =>
      array(
        'maphuongxa' => 40135065,
        'tenphuongxa' => 'Xã Yên Định',
      ),
      65 =>
      array(
        'maphuongxa' => 40135066,
        'tenphuongxa' => 'Xã Yên Trường',
      ),
      66 =>
      array(
        'maphuongxa' => 40135067,
        'tenphuongxa' => 'Xã Yên Phú',
      ),
      67 =>
      array(
        'maphuongxa' => 40135068,
        'tenphuongxa' => 'Xã Quý Lộc',
      ),
      68 =>
      array(
        'maphuongxa' => 40135069,
        'tenphuongxa' => 'Xã Yên Ninh',
      ),
      69 =>
      array(
        'maphuongxa' => 40135070,
        'tenphuongxa' => 'Xã Định Tân',
      ),
      70 =>
      array(
        'maphuongxa' => 40135071,
        'tenphuongxa' => 'Xã Định Hoà',
      ),
      71 =>
      array(
        'maphuongxa' => 40137072,
        'tenphuongxa' => 'Xã Thọ Xuân',
      ),
      72 =>
      array(
        'maphuongxa' => 40137073,
        'tenphuongxa' => 'Xã Thọ Long',
      ),
      73 =>
      array(
        'maphuongxa' => 40137074,
        'tenphuongxa' => 'Xã Xuân Hoà',
      ),
      74 =>
      array(
        'maphuongxa' => 40137075,
        'tenphuongxa' => 'Xã Sao Vàng',
      ),
      75 =>
      array(
        'maphuongxa' => 40137076,
        'tenphuongxa' => 'Xã Lam Sơn',
      ),
      76 =>
      array(
        'maphuongxa' => 40137077,
        'tenphuongxa' => 'Xã Thọ Lập',
      ),
      77 =>
      array(
        'maphuongxa' => 40137078,
        'tenphuongxa' => 'Xã Xuân Tín',
      ),
      78 =>
      array(
        'maphuongxa' => 40137079,
        'tenphuongxa' => 'Xã Xuân Lập',
      ),
      79 =>
      array(
        'maphuongxa' => 40129080,
        'tenphuongxa' => 'Xã Vĩnh Lộc',
      ),
      80 =>
      array(
        'maphuongxa' => 40129081,
        'tenphuongxa' => 'Xã Tây Đô',
      ),
      81 =>
      array(
        'maphuongxa' => 40129082,
        'tenphuongxa' => 'Xã Biện Thượng',
      ),
      82 =>
      array(
        'maphuongxa' => 40147083,
        'tenphuongxa' => 'Xã Triệu Sơn',
      ),
      83 =>
      array(
        'maphuongxa' => 40147084,
        'tenphuongxa' => 'Xã Thọ Bình',
      ),
      84 =>
      array(
        'maphuongxa' => 40147085,
        'tenphuongxa' => 'Xã Thọ Ngọc',
      ),
      85 =>
      array(
        'maphuongxa' => 40147086,
        'tenphuongxa' => 'Xã Thọ Phú',
      ),
      86 =>
      array(
        'maphuongxa' => 40147087,
        'tenphuongxa' => 'Xã Hợp Tiến',
      ),
      87 =>
      array(
        'maphuongxa' => 40147088,
        'tenphuongxa' => 'Xã An Nông',
      ),
      88 =>
      array(
        'maphuongxa' => 40147089,
        'tenphuongxa' => 'Xã Tân Ninh',
      ),
      89 =>
      array(
        'maphuongxa' => 40147090,
        'tenphuongxa' => 'Xã Đồng Tiến',
      ),
      90 =>
      array(
        'maphuongxa' => 40107091,
        'tenphuongxa' => 'Xã Mường Chanh',
      ),
      91 =>
      array(
        'maphuongxa' => 40107092,
        'tenphuongxa' => 'Xã Quang Chiểu',
      ),
      92 =>
      array(
        'maphuongxa' => 40107093,
        'tenphuongxa' => 'Xã Tam chung',
      ),
      93 =>
      array(
        'maphuongxa' => 40107094,
        'tenphuongxa' => 'Xã Mường Lát',
      ),
      94 =>
      array(
        'maphuongxa' => 40107095,
        'tenphuongxa' => 'Xã Pù Nhi',
      ),
      95 =>
      array(
        'maphuongxa' => 40107096,
        'tenphuongxa' => 'Xã Nhi Sơn',
      ),
      96 =>
      array(
        'maphuongxa' => 40107097,
        'tenphuongxa' => 'Xã Mường Lý',
      ),
      97 =>
      array(
        'maphuongxa' => 40107098,
        'tenphuongxa' => 'Xã Trung Lý',
      ),
      98 =>
      array(
        'maphuongxa' => 40109099,
        'tenphuongxa' => 'Xã Hồi Xuân',
      ),
      99 =>
      array(
        'maphuongxa' => 40109100,
        'tenphuongxa' => 'Xã Nam Xuân',
      ),
      100 =>
      array(
        'maphuongxa' => 40109101,
        'tenphuongxa' => 'Xã Thiên Phủ',
      ),
      101 =>
      array(
        'maphuongxa' => 40109102,
        'tenphuongxa' => 'Xã Hiền Kiệt',
      ),
      102 =>
      array(
        'maphuongxa' => 40109103,
        'tenphuongxa' => 'Xã Phú Xuân',
      ),
      103 =>
      array(
        'maphuongxa' => 40109104,
        'tenphuongxa' => 'Xã Phú Lệ',
      ),
      104 =>
      array(
        'maphuongxa' => 40109105,
        'tenphuongxa' => 'Xã Trung Thành',
      ),
      105 =>
      array(
        'maphuongxa' => 40109106,
        'tenphuongxa' => 'Xã Trung Sơn',
      ),
      106 =>
      array(
        'maphuongxa' => 40111107,
        'tenphuongxa' => 'Xã Na Mèo',
      ),
      107 =>
      array(
        'maphuongxa' => 40111108,
        'tenphuongxa' => 'Xã Sơn Thủy',
      ),
      108 =>
      array(
        'maphuongxa' => 40111109,
        'tenphuongxa' => 'Xã Sơn Điện',
      ),
      109 =>
      array(
        'maphuongxa' => 40111110,
        'tenphuongxa' => 'Xã Mường Mìn',
      ),
      110 =>
      array(
        'maphuongxa' => 40111111,
        'tenphuongxa' => 'Xã Tam Thanh',
      ),
      111 =>
      array(
        'maphuongxa' => 40111112,
        'tenphuongxa' => 'Xã Tam Lư',
      ),
      112 =>
      array(
        'maphuongxa' => 40111113,
        'tenphuongxa' => 'Xã Quan Sơn',
      ),
      113 =>
      array(
        'maphuongxa' => 40111114,
        'tenphuongxa' => 'Xã Trung Hạ',
      ),
      114 =>
      array(
        'maphuongxa' => 40117115,
        'tenphuongxa' => 'Xã Linh Sơn',
      ),
      115 =>
      array(
        'maphuongxa' => 40117116,
        'tenphuongxa' => 'Xã Đồng Lương',
      ),
      116 =>
      array(
        'maphuongxa' => 40117117,
        'tenphuongxa' => 'Xã Văn Phú',
      ),
      117 =>
      array(
        'maphuongxa' => 40117118,
        'tenphuongxa' => 'Xã Giao An',
      ),
      118 =>
      array(
        'maphuongxa' => 40117119,
        'tenphuongxa' => 'Xã Yên Khương',
      ),
      119 =>
      array(
        'maphuongxa' => 40117120,
        'tenphuongxa' => 'Xã Yên Thắng',
      ),
      120 =>
      array(
        'maphuongxa' => 40113121,
        'tenphuongxa' => 'Xã Văn Nho',
      ),
      121 =>
      array(
        'maphuongxa' => 40113122,
        'tenphuongxa' => 'Xã Thiết Ống',
      ),
      122 =>
      array(
        'maphuongxa' => 40113123,
        'tenphuongxa' => 'Xã Bá Thước',
      ),
      123 =>
      array(
        'maphuongxa' => 40113124,
        'tenphuongxa' => 'Xã Cổ Lũng',
      ),
      124 =>
      array(
        'maphuongxa' => 40113125,
        'tenphuongxa' => 'Xã Pù Luông',
      ),
      125 =>
      array(
        'maphuongxa' => 40113126,
        'tenphuongxa' => 'Xã Điền Lư',
      ),
      126 =>
      array(
        'maphuongxa' => 40113127,
        'tenphuongxa' => 'Xã Điền Quang',
      ),
      127 =>
      array(
        'maphuongxa' => 40113128,
        'tenphuongxa' => 'Xã Quý Lương',
      ),
      128 =>
      array(
        'maphuongxa' => 40121129,
        'tenphuongxa' => 'Xã Ngọc Lặc',
      ),
      129 =>
      array(
        'maphuongxa' => 40121130,
        'tenphuongxa' => 'Xã Thạch Lập',
      ),
      130 =>
      array(
        'maphuongxa' => 40121131,
        'tenphuongxa' => 'Xã Ngọc Liên',
      ),
      131 =>
      array(
        'maphuongxa' => 40121132,
        'tenphuongxa' => 'Xã Minh Sơn',
      ),
      132 =>
      array(
        'maphuongxa' => 40121133,
        'tenphuongxa' => 'Xã Nguyệt Ấn',
      ),
      133 =>
      array(
        'maphuongxa' => 40121134,
        'tenphuongxa' => 'Xã Kiên Thọ',
      ),
      134 =>
      array(
        'maphuongxa' => 40115135,
        'tenphuongxa' => 'Xã Cẩm Thạch',
      ),
      135 =>
      array(
        'maphuongxa' => 40115136,
        'tenphuongxa' => 'Xã Cẩm Thủy',
      ),
      136 =>
      array(
        'maphuongxa' => 40115137,
        'tenphuongxa' => 'Xã Cẩm Tú',
      ),
      137 =>
      array(
        'maphuongxa' => 40115138,
        'tenphuongxa' => 'Xã Cẩm Vân',
      ),
      138 =>
      array(
        'maphuongxa' => 40115139,
        'tenphuongxa' => 'Xã Cẩm Tân',
      ),
      139 =>
      array(
        'maphuongxa' => 40119140,
        'tenphuongxa' => 'Xã Kim Tân',
      ),
      140 =>
      array(
        'maphuongxa' => 40119141,
        'tenphuongxa' => 'Xã Vân Du',
      ),
      141 =>
      array(
        'maphuongxa' => 40119142,
        'tenphuongxa' => 'Xã Ngọc Trạo',
      ),
      142 =>
      array(
        'maphuongxa' => 40119143,
        'tenphuongxa' => 'Xã Thạch Bình',
      ),
      143 =>
      array(
        'maphuongxa' => 40119144,
        'tenphuongxa' => 'Xã Thành Vinh',
      ),
      144 =>
      array(
        'maphuongxa' => 40119145,
        'tenphuongxa' => 'Xã Thạch Quảng',
      ),
      145 =>
      array(
        'maphuongxa' => 40125146,
        'tenphuongxa' => 'Xã Như Xuân',
      ),
      146 =>
      array(
        'maphuongxa' => 40125147,
        'tenphuongxa' => 'Xã Thượng Ninh',
      ),
      147 =>
      array(
        'maphuongxa' => 40125148,
        'tenphuongxa' => 'Xã Xuân Bình',
      ),
      148 =>
      array(
        'maphuongxa' => 40125149,
        'tenphuongxa' => 'Xã Hóa Quỳ',
      ),
      149 =>
      array(
        'maphuongxa' => 40125150,
        'tenphuongxa' => 'Xã Thanh Quân',
      ),
      150 =>
      array(
        'maphuongxa' => 40125151,
        'tenphuongxa' => 'Xã Thanh Phong',
      ),
      151 =>
      array(
        'maphuongxa' => 40127152,
        'tenphuongxa' => 'Xã Xuân Du',
      ),
      152 =>
      array(
        'maphuongxa' => 40127153,
        'tenphuongxa' => 'Xã Mậu Lâm',
      ),
      153 =>
      array(
        'maphuongxa' => 40127154,
        'tenphuongxa' => 'Xã Như Thanh',
      ),
      154 =>
      array(
        'maphuongxa' => 40127155,
        'tenphuongxa' => 'Xã Yên Thọ',
      ),
      155 =>
      array(
        'maphuongxa' => 40127156,
        'tenphuongxa' => 'Xã Xuân Thái',
      ),
      156 =>
      array(
        'maphuongxa' => 40127157,
        'tenphuongxa' => 'Xã Thanh Kỳ',
      ),
      157 =>
      array(
        'maphuongxa' => 40123158,
        'tenphuongxa' => 'Xã Bát Mọt',
      ),
      158 =>
      array(
        'maphuongxa' => 40123159,
        'tenphuongxa' => 'Xã Yên Nhân',
      ),
      159 =>
      array(
        'maphuongxa' => 40123160,
        'tenphuongxa' => 'Xã Lương Sơn',
      ),
      160 =>
      array(
        'maphuongxa' => 40123161,
        'tenphuongxa' => 'Xã Thường Xuân',
      ),
      161 =>
      array(
        'maphuongxa' => 40123162,
        'tenphuongxa' => 'Xã Luận Thành',
      ),
      162 =>
      array(
        'maphuongxa' => 40123163,
        'tenphuongxa' => 'Xã Tân Thành',
      ),
      163 =>
      array(
        'maphuongxa' => 40123164,
        'tenphuongxa' => 'Xã Vạn Xuân',
      ),
      164 =>
      array(
        'maphuongxa' => 40123165,
        'tenphuongxa' => 'Xã Thắng Lộc',
      ),
      165 =>
      array(
        'maphuongxa' => 40123166,
        'tenphuongxa' => 'Xã Xuân Chinh',
      ),
    ),
  ),
  16 =>
  array(
    'matinhBNV' => 17,
    'matinhTMS' => '403',
    'tentinhmoi' => 'Tỉnh Nghệ An',
    'phuongxa' =>
    array(
      0 =>
      array(
        'maphuongxa' => 40327001,
        'tenphuongxa' => 'Xã Anh Sơn',
      ),
      1 =>
      array(
        'maphuongxa' => 40327002,
        'tenphuongxa' => 'Xã Yên Xuân',
      ),
      2 =>
      array(
        'maphuongxa' => 40327003,
        'tenphuongxa' => 'Xã Nhân Hoà',
      ),
      3 =>
      array(
        'maphuongxa' => 40327004,
        'tenphuongxa' => 'Xã Anh Sơn Đông',
      ),
      4 =>
      array(
        'maphuongxa' => 40327005,
        'tenphuongxa' => 'Xã Vĩnh Tường',
      ),
      5 =>
      array(
        'maphuongxa' => 40327006,
        'tenphuongxa' => 'Xã Thành Bình Thọ',
      ),
      6 =>
      array(
        'maphuongxa' => 40321007,
        'tenphuongxa' => 'Xã Con Cuông',
      ),
      7 =>
      array(
        'maphuongxa' => 40321008,
        'tenphuongxa' => 'Xã Môn Sơn',
      ),
      8 =>
      array(
        'maphuongxa' => 40321009,
        'tenphuongxa' => 'Xã Mậu Thạch',
      ),
      9 =>
      array(
        'maphuongxa' => 40321010,
        'tenphuongxa' => 'Xã Cam Phục',
      ),
      10 =>
      array(
        'maphuongxa' => 40321011,
        'tenphuongxa' => 'Xã Châu Khê',
      ),
      11 =>
      array(
        'maphuongxa' => 40321012,
        'tenphuongxa' => 'Xã Bình Chuẩn',
      ),
      12 =>
      array(
        'maphuongxa' => 40325013,
        'tenphuongxa' => 'Xã Diễn Châu',
      ),
      13 =>
      array(
        'maphuongxa' => 40325014,
        'tenphuongxa' => 'Xã Đức Châu',
      ),
      14 =>
      array(
        'maphuongxa' => 40325015,
        'tenphuongxa' => 'Xã Quảng Châu',
      ),
      15 =>
      array(
        'maphuongxa' => 40325016,
        'tenphuongxa' => 'Xã Hải Châu',
      ),
      16 =>
      array(
        'maphuongxa' => 40325017,
        'tenphuongxa' => 'Xã Tân Châu',
      ),
      17 =>
      array(
        'maphuongxa' => 40325018,
        'tenphuongxa' => 'Xã An Châu',
      ),
      18 =>
      array(
        'maphuongxa' => 40325019,
        'tenphuongxa' => 'Xã Minh Châu',
      ),
      19 =>
      array(
        'maphuongxa' => 40325020,
        'tenphuongxa' => 'Xã Hùng Châu',
      ),
      20 =>
      array(
        'maphuongxa' => 40329021,
        'tenphuongxa' => 'Xã Đô Lương',
      ),
      21 =>
      array(
        'maphuongxa' => 40329022,
        'tenphuongxa' => 'Xã Bạch Ngọc',
      ),
      22 =>
      array(
        'maphuongxa' => 40329023,
        'tenphuongxa' => 'Xã Văn Hiến',
      ),
      23 =>
      array(
        'maphuongxa' => 40329024,
        'tenphuongxa' => 'Xã Bạch Hà',
      ),
      24 =>
      array(
        'maphuongxa' => 40329025,
        'tenphuongxa' => 'Xã Thuần Trung',
      ),
      25 =>
      array(
        'maphuongxa' => 40329026,
        'tenphuongxa' => 'Xã Lương Sơn',
      ),
      26 =>
      array(
        'maphuongxa' => 40339027,
        'tenphuongxa' => 'Phường Hoàng Mai',
      ),
      27 =>
      array(
        'maphuongxa' => 40339028,
        'tenphuongxa' => 'Phường Tân Mai',
      ),
      28 =>
      array(
        'maphuongxa' => 40339029,
        'tenphuongxa' => 'Phường Quỳnh Mai',
      ),
      29 =>
      array(
        'maphuongxa' => 40337030,
        'tenphuongxa' => 'Xã Hưng Nguyên',
      ),
      30 =>
      array(
        'maphuongxa' => 40337031,
        'tenphuongxa' => 'Xã Yên Trung',
      ),
      31 =>
      array(
        'maphuongxa' => 40337032,
        'tenphuongxa' => 'Xã Hưng Nguyên Nam',
      ),
      32 =>
      array(
        'maphuongxa' => 40337033,
        'tenphuongxa' => 'Xã Lam Thành',
      ),
      33 =>
      array(
        'maphuongxa' => 40309034,
        'tenphuongxa' => 'Xã Mường Xén',
      ),
      34 =>
      array(
        'maphuongxa' => 40309035,
        'tenphuongxa' => 'Xã Hữu Kiệm',
      ),
      35 =>
      array(
        'maphuongxa' => 40309036,
        'tenphuongxa' => 'Xã Nậm Cắn',
      ),
      36 =>
      array(
        'maphuongxa' => 40309037,
        'tenphuongxa' => 'Xã Chiêu Lưu',
      ),
      37 =>
      array(
        'maphuongxa' => 40309038,
        'tenphuongxa' => 'Xã Na Loi',
      ),
      38 =>
      array(
        'maphuongxa' => 40309039,
        'tenphuongxa' => 'Xã Mường Típ',
      ),
      39 =>
      array(
        'maphuongxa' => 40309040,
        'tenphuongxa' => 'Xã Na Ngoi',
      ),
      40 =>
      array(
        'maphuongxa' => 40309041,
        'tenphuongxa' => 'Xã Mỹ Lý',
      ),
      41 =>
      array(
        'maphuongxa' => 40309042,
        'tenphuongxa' => 'Xã Bắc Lý',
      ),
      42 =>
      array(
        'maphuongxa' => 40309043,
        'tenphuongxa' => 'Xã Keng Đu',
      ),
      43 =>
      array(
        'maphuongxa' => 40309044,
        'tenphuongxa' => 'Xã Huồi Tụ',
      ),
      44 =>
      array(
        'maphuongxa' => 40309045,
        'tenphuongxa' => 'Xã Mường Lống',
      ),
      45 =>
      array(
        'maphuongxa' => 40335046,
        'tenphuongxa' => 'Xã Vạn An',
      ),
      46 =>
      array(
        'maphuongxa' => 40335047,
        'tenphuongxa' => 'Xã Nam Đàn',
      ),
      47 =>
      array(
        'maphuongxa' => 40335048,
        'tenphuongxa' => 'Xã Đại Huệ',
      ),
      48 =>
      array(
        'maphuongxa' => 40335049,
        'tenphuongxa' => 'Xã Thiên Nhẫn',
      ),
      49 =>
      array(
        'maphuongxa' => 40335050,
        'tenphuongxa' => 'Xã Kim Liên',
      ),
      50 =>
      array(
        'maphuongxa' => 40313051,
        'tenphuongxa' => 'Xã Nghĩa Đàn',
      ),
      51 =>
      array(
        'maphuongxa' => 40313052,
        'tenphuongxa' => 'Xã Nghĩa Thọ',
      ),
      52 =>
      array(
        'maphuongxa' => 40313053,
        'tenphuongxa' => 'Xã Nghĩa Lâm',
      ),
      53 =>
      array(
        'maphuongxa' => 40313054,
        'tenphuongxa' => 'Xã Nghĩa Mai',
      ),
      54 =>
      array(
        'maphuongxa' => 40313055,
        'tenphuongxa' => 'Xã Nghĩa Hưng',
      ),
      55 =>
      array(
        'maphuongxa' => 40313056,
        'tenphuongxa' => 'Xã Nghĩa Khánh',
      ),
      56 =>
      array(
        'maphuongxa' => 40313057,
        'tenphuongxa' => 'Xã Nghĩa Lộc',
      ),
      57 =>
      array(
        'maphuongxa' => 40333058,
        'tenphuongxa' => 'Xã Nghi Lộc',
      ),
      58 =>
      array(
        'maphuongxa' => 40333059,
        'tenphuongxa' => 'Xã Phúc Lộc',
      ),
      59 =>
      array(
        'maphuongxa' => 40333060,
        'tenphuongxa' => 'Xã Đông Lộc',
      ),
      60 =>
      array(
        'maphuongxa' => 40333061,
        'tenphuongxa' => 'Xã Trung Lộc',
      ),
      61 =>
      array(
        'maphuongxa' => 40333062,
        'tenphuongxa' => 'Xã Thần Lĩnh',
      ),
      62 =>
      array(
        'maphuongxa' => 40333063,
        'tenphuongxa' => 'Xã Hải Lộc',
      ),
      63 =>
      array(
        'maphuongxa' => 40333064,
        'tenphuongxa' => 'Xã Văn Kiều',
      ),
      64 =>
      array(
        'maphuongxa' => 40305065,
        'tenphuongxa' => 'Xã Quế Phong',
      ),
      65 =>
      array(
        'maphuongxa' => 40305066,
        'tenphuongxa' => 'Xã Tiền Phong',
      ),
      66 =>
      array(
        'maphuongxa' => 40305067,
        'tenphuongxa' => 'Xã Tri Lễ',
      ),
      67 =>
      array(
        'maphuongxa' => 40305068,
        'tenphuongxa' => 'Xã Mường Quàng',
      ),
      68 =>
      array(
        'maphuongxa' => 40305069,
        'tenphuongxa' => 'Xã Thông Thụ',
      ),
      69 =>
      array(
        'maphuongxa' => 40307070,
        'tenphuongxa' => 'Xã Quỳ Châu',
      ),
      70 =>
      array(
        'maphuongxa' => 40307071,
        'tenphuongxa' => 'Xã Châu Tiến',
      ),
      71 =>
      array(
        'maphuongxa' => 40307072,
        'tenphuongxa' => 'Xã Hùng Chân',
      ),
      72 =>
      array(
        'maphuongxa' => 40307073,
        'tenphuongxa' => 'Xã Châu Bình',
      ),
      73 =>
      array(
        'maphuongxa' => 40311074,
        'tenphuongxa' => 'Xã Quỳ Hợp',
      ),
      74 =>
      array(
        'maphuongxa' => 40311075,
        'tenphuongxa' => 'Xã Tam Hợp',
      ),
      75 =>
      array(
        'maphuongxa' => 40311076,
        'tenphuongxa' => 'Xã Châu Lộc',
      ),
      76 =>
      array(
        'maphuongxa' => 40311077,
        'tenphuongxa' => 'Xã Châu Hồng',
      ),
      77 =>
      array(
        'maphuongxa' => 40311078,
        'tenphuongxa' => 'Xã Mường Ham',
      ),
      78 =>
      array(
        'maphuongxa' => 40311079,
        'tenphuongxa' => 'Xã Mường Chọng',
      ),
      79 =>
      array(
        'maphuongxa' => 40311080,
        'tenphuongxa' => 'Xã Minh Hợp',
      ),
      80 =>
      array(
        'maphuongxa' => 40317081,
        'tenphuongxa' => 'Xã Quỳnh Lưu',
      ),
      81 =>
      array(
        'maphuongxa' => 40317082,
        'tenphuongxa' => 'Xã Quỳnh Văn',
      ),
      82 =>
      array(
        'maphuongxa' => 40317083,
        'tenphuongxa' => 'Xã Quỳnh Anh',
      ),
      83 =>
      array(
        'maphuongxa' => 40317084,
        'tenphuongxa' => 'Xã Quỳnh Tam',
      ),
      84 =>
      array(
        'maphuongxa' => 40317085,
        'tenphuongxa' => 'Xã Quỳnh Phú',
      ),
      85 =>
      array(
        'maphuongxa' => 40317086,
        'tenphuongxa' => 'Xã Quỳnh Sơn',
      ),
      86 =>
      array(
        'maphuongxa' => 40317087,
        'tenphuongxa' => 'Xã Quỳnh Thắng',
      ),
      87 =>
      array(
        'maphuongxa' => 40319088,
        'tenphuongxa' => 'Xã Tân Kỳ',
      ),
      88 =>
      array(
        'maphuongxa' => 40319089,
        'tenphuongxa' => 'Xã Tân Phú',
      ),
      89 =>
      array(
        'maphuongxa' => 40319090,
        'tenphuongxa' => 'Xã Tân An',
      ),
      90 =>
      array(
        'maphuongxa' => 40319091,
        'tenphuongxa' => 'Xã Nghĩa Đồng',
      ),
      91 =>
      array(
        'maphuongxa' => 40319092,
        'tenphuongxa' => 'Xã Giai Xuân',
      ),
      92 =>
      array(
        'maphuongxa' => 40319093,
        'tenphuongxa' => 'Xã Nghĩa Hành',
      ),
      93 =>
      array(
        'maphuongxa' => 40319094,
        'tenphuongxa' => 'Xã Tiên Đồng',
      ),
      94 =>
      array(
        'maphuongxa' => 40314095,
        'tenphuongxa' => 'Phường Thái Hoà',
      ),
      95 =>
      array(
        'maphuongxa' => 40314096,
        'tenphuongxa' => 'Phường Tây Hiếu',
      ),
      96 =>
      array(
        'maphuongxa' => 40314097,
        'tenphuongxa' => 'Xã Đông Hiếu',
      ),
      97 =>
      array(
        'maphuongxa' => 40331098,
        'tenphuongxa' => 'Xã Cát Ngạn',
      ),
      98 =>
      array(
        'maphuongxa' => 40331099,
        'tenphuongxa' => 'Xã Tam Đồng',
      ),
      99 =>
      array(
        'maphuongxa' => 40331100,
        'tenphuongxa' => 'Xã Hạnh Lâm',
      ),
      100 =>
      array(
        'maphuongxa' => 40331101,
        'tenphuongxa' => 'Xã Sơn Lâm',
      ),
      101 =>
      array(
        'maphuongxa' => 40331102,
        'tenphuongxa' => 'Xã Hoa Quân',
      ),
      102 =>
      array(
        'maphuongxa' => 40331103,
        'tenphuongxa' => 'Xã Kim Bảng',
      ),
      103 =>
      array(
        'maphuongxa' => 40331104,
        'tenphuongxa' => 'Xã Bích Hào',
      ),
      104 =>
      array(
        'maphuongxa' => 40331105,
        'tenphuongxa' => 'Xã Đại Đồng',
      ),
      105 =>
      array(
        'maphuongxa' => 40331106,
        'tenphuongxa' => 'Xã Xuân Lâm',
      ),
      106 =>
      array(
        'maphuongxa' => 40315107,
        'tenphuongxa' => 'Xã Tam Quang',
      ),
      107 =>
      array(
        'maphuongxa' => 40315108,
        'tenphuongxa' => 'Xã Tam Thái',
      ),
      108 =>
      array(
        'maphuongxa' => 40315109,
        'tenphuongxa' => 'Xã Tương Dương',
      ),
      109 =>
      array(
        'maphuongxa' => 40315110,
        'tenphuongxa' => 'Xã Lượng Minh',
      ),
      110 =>
      array(
        'maphuongxa' => 40315111,
        'tenphuongxa' => 'Xã Yên Na',
      ),
      111 =>
      array(
        'maphuongxa' => 40315112,
        'tenphuongxa' => 'Xã Yên Hoà',
      ),
      112 =>
      array(
        'maphuongxa' => 40315113,
        'tenphuongxa' => 'Xã Nga My',
      ),
      113 =>
      array(
        'maphuongxa' => 40315114,
        'tenphuongxa' => 'Xã Hữu Khuông',
      ),
      114 =>
      array(
        'maphuongxa' => 40315115,
        'tenphuongxa' => 'Xã Nhôn Mai',
      ),
      115 =>
      array(
        'maphuongxa' => 40301116,
        'tenphuongxa' => 'Phường Trường Vinh',
      ),
      116 =>
      array(
        'maphuongxa' => 40301117,
        'tenphuongxa' => 'Phường Thành Vinh',
      ),
      117 =>
      array(
        'maphuongxa' => 40301118,
        'tenphuongxa' => 'Phường Vinh Hưng',
      ),
      118 =>
      array(
        'maphuongxa' => 40301119,
        'tenphuongxa' => 'Phường Vinh Phú',
      ),
      119 =>
      array(
        'maphuongxa' => 40301120,
        'tenphuongxa' => 'Phường Vinh Lộc',
      ),
      120 =>
      array(
        'maphuongxa' => 40301121,
        'tenphuongxa' => 'Phường Cửa Lò',
      ),
      121 =>
      array(
        'maphuongxa' => 40323122,
        'tenphuongxa' => 'Xã Yên Thành',
      ),
      122 =>
      array(
        'maphuongxa' => 40323123,
        'tenphuongxa' => 'Xã Quan Thành',
      ),
      123 =>
      array(
        'maphuongxa' => 40323124,
        'tenphuongxa' => 'Xã Hợp Minh',
      ),
      124 =>
      array(
        'maphuongxa' => 40323125,
        'tenphuongxa' => 'Xã Vân Tụ',
      ),
      125 =>
      array(
        'maphuongxa' => 40323126,
        'tenphuongxa' => 'Xã Vân Du',
      ),
      126 =>
      array(
        'maphuongxa' => 40323127,
        'tenphuongxa' => 'Xã Quang Đồng',
      ),
      127 =>
      array(
        'maphuongxa' => 40323128,
        'tenphuongxa' => 'Xã Giai Lạc',
      ),
      128 =>
      array(
        'maphuongxa' => 40323129,
        'tenphuongxa' => 'Xã Bình Minh',
      ),
      129 =>
      array(
        'maphuongxa' => 40323130,
        'tenphuongxa' => 'Xã Đông Thành',
      ),
    ),
  ),
  17 =>
  array(
    'matinhBNV' => 18,
    'matinhTMS' => '405',
    'tentinhmoi' => 'Tỉnh Hà Tĩnh',
    'phuongxa' =>
    array(
      0 =>
      array(
        'maphuongxa' => 40520001,
        'tenphuongxa' => 'Phường Sông Trí',
      ),
      1 =>
      array(
        'maphuongxa' => 40520002,
        'tenphuongxa' => 'Phường Hải Ninh',
      ),
      2 =>
      array(
        'maphuongxa' => 40520003,
        'tenphuongxa' => 'Phường Hoành Sơn',
      ),
      3 =>
      array(
        'maphuongxa' => 40520004,
        'tenphuongxa' => 'Phường Vũng Áng',
      ),
      4 =>
      array(
        'maphuongxa' => 40519005,
        'tenphuongxa' => 'Xã Kỳ Xuân',
      ),
      5 =>
      array(
        'maphuongxa' => 40519006,
        'tenphuongxa' => 'Xã Kỳ Anh',
      ),
      6 =>
      array(
        'maphuongxa' => 40519007,
        'tenphuongxa' => 'Xã Kỳ Hoa',
      ),
      7 =>
      array(
        'maphuongxa' => 40519008,
        'tenphuongxa' => 'Xã Kỳ Văn',
      ),
      8 =>
      array(
        'maphuongxa' => 40519009,
        'tenphuongxa' => 'Xã Kỳ Khang',
      ),
      9 =>
      array(
        'maphuongxa' => 40519010,
        'tenphuongxa' => 'Xã Kỳ Lạc',
      ),
      10 =>
      array(
        'maphuongxa' => 40519011,
        'tenphuongxa' => 'Xã Kỳ Thượng',
      ),
      11 =>
      array(
        'maphuongxa' => 40515012,
        'tenphuongxa' => 'Xã Cẩm Xuyên',
      ),
      12 =>
      array(
        'maphuongxa' => 40515013,
        'tenphuongxa' => 'Xã Thiên Cầm',
      ),
      13 =>
      array(
        'maphuongxa' => 40515014,
        'tenphuongxa' => 'Xã Cẩm Duệ',
      ),
      14 =>
      array(
        'maphuongxa' => 40515015,
        'tenphuongxa' => 'Xã Cẩm Hưng',
      ),
      15 =>
      array(
        'maphuongxa' => 40515016,
        'tenphuongxa' => 'Xã Cẩm Lạc',
      ),
      16 =>
      array(
        'maphuongxa' => 40515017,
        'tenphuongxa' => 'Xã Cẩm Trung',
      ),
      17 =>
      array(
        'maphuongxa' => 40515018,
        'tenphuongxa' => 'Xã Yên Hoà',
      ),
      18 =>
      array(
        'maphuongxa' => 40501019,
        'tenphuongxa' => 'Phường Thành Sen',
      ),
      19 =>
      array(
        'maphuongxa' => 40501020,
        'tenphuongxa' => 'Phường Trần Phú',
      ),
      20 =>
      array(
        'maphuongxa' => 40501021,
        'tenphuongxa' => 'Phường Hà Huy Tập',
      ),
      21 =>
      array(
        'maphuongxa' => 40501022,
        'tenphuongxa' => 'Xã Thạch Lạc',
      ),
      22 =>
      array(
        'maphuongxa' => 40501023,
        'tenphuongxa' => 'Xã Đồng Tiến',
      ),
      23 =>
      array(
        'maphuongxa' => 40501024,
        'tenphuongxa' => 'Xã Thạch Khê',
      ),
      24 =>
      array(
        'maphuongxa' => 40501025,
        'tenphuongxa' => 'Xã Cẩm Bình',
      ),
      25 =>
      array(
        'maphuongxa' => 40513026,
        'tenphuongxa' => 'Xã Thạch Hà',
      ),
      26 =>
      array(
        'maphuongxa' => 40513027,
        'tenphuongxa' => 'Xã Toàn Lưu',
      ),
      27 =>
      array(
        'maphuongxa' => 40513028,
        'tenphuongxa' => 'Xã Việt Xuyên',
      ),
      28 =>
      array(
        'maphuongxa' => 40513029,
        'tenphuongxa' => 'Xã Đông Kinh',
      ),
      29 =>
      array(
        'maphuongxa' => 40513030,
        'tenphuongxa' => 'Xã Thạch Xuân',
      ),
      30 =>
      array(
        'maphuongxa' => 40513031,
        'tenphuongxa' => 'Xã Lộc Hà',
      ),
      31 =>
      array(
        'maphuongxa' => 40513032,
        'tenphuongxa' => 'Xã Hồng Lộc',
      ),
      32 =>
      array(
        'maphuongxa' => 40513033,
        'tenphuongxa' => 'Xã Mai Phụ',
      ),
      33 =>
      array(
        'maphuongxa' => 40511034,
        'tenphuongxa' => 'Xã Can Lộc',
      ),
      34 =>
      array(
        'maphuongxa' => 40511035,
        'tenphuongxa' => 'Xã Tùng Lộc',
      ),
      35 =>
      array(
        'maphuongxa' => 40511036,
        'tenphuongxa' => 'Xã Gia Hanh',
      ),
      36 =>
      array(
        'maphuongxa' => 40511037,
        'tenphuongxa' => 'Xã Trường Lưu',
      ),
      37 =>
      array(
        'maphuongxa' => 40511038,
        'tenphuongxa' => 'Xã Xuân Lộc',
      ),
      38 =>
      array(
        'maphuongxa' => 40511039,
        'tenphuongxa' => 'Xã Đồng Lộc',
      ),
      39 =>
      array(
        'maphuongxa' => 40503040,
        'tenphuongxa' => 'Phường Bắc Hồng Lĩnh',
      ),
      40 =>
      array(
        'maphuongxa' => 40503041,
        'tenphuongxa' => 'Phường Nam Hồng Lĩnh',
      ),
      41 =>
      array(
        'maphuongxa' => 40505042,
        'tenphuongxa' => 'Xã Tiên Điền',
      ),
      42 =>
      array(
        'maphuongxa' => 40505043,
        'tenphuongxa' => 'Xã Nghi Xuân',
      ),
      43 =>
      array(
        'maphuongxa' => 40505044,
        'tenphuongxa' => 'Xã Cổ Đạm',
      ),
      44 =>
      array(
        'maphuongxa' => 40505045,
        'tenphuongxa' => 'Xã Đan Hải',
      ),
      45 =>
      array(
        'maphuongxa' => 40507046,
        'tenphuongxa' => 'Xã Đức Thọ',
      ),
      46 =>
      array(
        'maphuongxa' => 40507047,
        'tenphuongxa' => 'Xã Đức Quang',
      ),
      47 =>
      array(
        'maphuongxa' => 40507048,
        'tenphuongxa' => 'Xã Đức Đồng',
      ),
      48 =>
      array(
        'maphuongxa' => 40507049,
        'tenphuongxa' => 'Xã Đức Thịnh',
      ),
      49 =>
      array(
        'maphuongxa' => 40507050,
        'tenphuongxa' => 'Xã Đức Minh',
      ),
      50 =>
      array(
        'maphuongxa' => 40509051,
        'tenphuongxa' => 'Xã Hương Sơn',
      ),
      51 =>
      array(
        'maphuongxa' => 40509052,
        'tenphuongxa' => 'Xã Sơn Tây',
      ),
      52 =>
      array(
        'maphuongxa' => 40509053,
        'tenphuongxa' => 'Xã Tứ Mỹ',
      ),
      53 =>
      array(
        'maphuongxa' => 40509054,
        'tenphuongxa' => 'Xã Sơn Giang',
      ),
      54 =>
      array(
        'maphuongxa' => 40509055,
        'tenphuongxa' => 'Xã Sơn Tiến',
      ),
      55 =>
      array(
        'maphuongxa' => 40509056,
        'tenphuongxa' => 'Xã Sơn Hồng',
      ),
      56 =>
      array(
        'maphuongxa' => 40509057,
        'tenphuongxa' => 'Xã Kim Hoa',
      ),
      57 =>
      array(
        'maphuongxa' => 40521058,
        'tenphuongxa' => 'Xã Vũ Quang',
      ),
      58 =>
      array(
        'maphuongxa' => 40521059,
        'tenphuongxa' => 'Xã Mai Hoa',
      ),
      59 =>
      array(
        'maphuongxa' => 40521060,
        'tenphuongxa' => 'Xã Thượng Đức',
      ),
      60 =>
      array(
        'maphuongxa' => 40517061,
        'tenphuongxa' => 'Xã Hương Khê',
      ),
      61 =>
      array(
        'maphuongxa' => 40517062,
        'tenphuongxa' => 'Xã Hương Phố',
      ),
      62 =>
      array(
        'maphuongxa' => 40517063,
        'tenphuongxa' => 'Xã Hương Đô',
      ),
      63 =>
      array(
        'maphuongxa' => 40517064,
        'tenphuongxa' => 'Xã Hà Linh',
      ),
      64 =>
      array(
        'maphuongxa' => 40517065,
        'tenphuongxa' => 'Xã Hương Bình',
      ),
      65 =>
      array(
        'maphuongxa' => 40517066,
        'tenphuongxa' => 'Xã Phúc Trạch',
      ),
      66 =>
      array(
        'maphuongxa' => 40517067,
        'tenphuongxa' => 'Xã Hương Xuân',
      ),
      67 =>
      array(
        'maphuongxa' => 40509068,
        'tenphuongxa' => 'Xã Sơn Kim 1',
      ),
      68 =>
      array(
        'maphuongxa' => 40509069,
        'tenphuongxa' => 'Xã Sơn Kim 2',
      ),
    ),
  ),
  18 =>
  array(
    'matinhBNV' => 19,
    'matinhTMS' => '409',
    'tentinhmoi' => 'Tỉnh Quảng Trị',
    'phuongxa' =>
    array(
      0 =>
      array(
        'maphuongxa' => 40701001,
        'tenphuongxa' => 'Phường Đồng Hới',
      ),
      1 =>
      array(
        'maphuongxa' => 40701002,
        'tenphuongxa' => 'Phường Đồng Thuận',
      ),
      2 =>
      array(
        'maphuongxa' => 40701003,
        'tenphuongxa' => 'Phường Đồng Sơn',
      ),
      3 =>
      array(
        'maphuongxa' => 40715004,
        'tenphuongxa' => 'Xã Nam Gianh',
      ),
      4 =>
      array(
        'maphuongxa' => 40715005,
        'tenphuongxa' => 'Xã Nam Ba Đồn',
      ),
      5 =>
      array(
        'maphuongxa' => 40715006,
        'tenphuongxa' => 'Phường Ba Đồn',
      ),
      6 =>
      array(
        'maphuongxa' => 40715007,
        'tenphuongxa' => 'Phường Bắc Gianh',
      ),
      7 =>
      array(
        'maphuongxa' => 40705008,
        'tenphuongxa' => 'Xã Dân Hóa',
      ),
      8 =>
      array(
        'maphuongxa' => 40705009,
        'tenphuongxa' => 'Xã Kim Điền',
      ),
      9 =>
      array(
        'maphuongxa' => 40705010,
        'tenphuongxa' => 'Xã Kim Phú',
      ),
      10 =>
      array(
        'maphuongxa' => 40705011,
        'tenphuongxa' => 'Xã Minh Hóa',
      ),
      11 =>
      array(
        'maphuongxa' => 40705012,
        'tenphuongxa' => 'Xã Tân Thành',
      ),
      12 =>
      array(
        'maphuongxa' => 40703013,
        'tenphuongxa' => 'Xã Tuyên Lâm',
      ),
      13 =>
      array(
        'maphuongxa' => 40703014,
        'tenphuongxa' => 'Xã Tuyên Sơn',
      ),
      14 =>
      array(
        'maphuongxa' => 40703015,
        'tenphuongxa' => 'Xã Đồng Lê',
      ),
      15 =>
      array(
        'maphuongxa' => 40703016,
        'tenphuongxa' => 'Xã Tuyên Phú',
      ),
      16 =>
      array(
        'maphuongxa' => 40703017,
        'tenphuongxa' => 'Xã Tuyên Bình',
      ),
      17 =>
      array(
        'maphuongxa' => 40703018,
        'tenphuongxa' => 'Xã Tuyên Hóa',
      ),
      18 =>
      array(
        'maphuongxa' => 40707019,
        'tenphuongxa' => 'Xã Tân Gianh',
      ),
      19 =>
      array(
        'maphuongxa' => 40707020,
        'tenphuongxa' => 'Xã Trung Thuần',
      ),
      20 =>
      array(
        'maphuongxa' => 40707021,
        'tenphuongxa' => 'Xã Quảng Trạch',
      ),
      21 =>
      array(
        'maphuongxa' => 40707022,
        'tenphuongxa' => 'Xã Hoà Trạch',
      ),
      22 =>
      array(
        'maphuongxa' => 40707023,
        'tenphuongxa' => 'Xã Phú Trạch',
      ),
      23 =>
      array(
        'maphuongxa' => 40709024,
        'tenphuongxa' => 'Xã Thượng Trạch',
      ),
      24 =>
      array(
        'maphuongxa' => 40709025,
        'tenphuongxa' => 'Xã Phong Nha',
      ),
      25 =>
      array(
        'maphuongxa' => 40709026,
        'tenphuongxa' => 'Xã Bắc Trạch',
      ),
      26 =>
      array(
        'maphuongxa' => 40709027,
        'tenphuongxa' => 'Xã Đông Trạch',
      ),
      27 =>
      array(
        'maphuongxa' => 40709028,
        'tenphuongxa' => 'Xã Hoàn Lão',
      ),
      28 =>
      array(
        'maphuongxa' => 40709029,
        'tenphuongxa' => 'Xã Bố Trạch',
      ),
      29 =>
      array(
        'maphuongxa' => 40709030,
        'tenphuongxa' => 'Xã Nam Trạch',
      ),
      30 =>
      array(
        'maphuongxa' => 40711031,
        'tenphuongxa' => 'Xã Quảng Ninh',
      ),
      31 =>
      array(
        'maphuongxa' => 40711032,
        'tenphuongxa' => 'Xã Ninh Châu',
      ),
      32 =>
      array(
        'maphuongxa' => 40711033,
        'tenphuongxa' => 'Xã Trường Ninh',
      ),
      33 =>
      array(
        'maphuongxa' => 40711034,
        'tenphuongxa' => 'Xã Trường Sơn',
      ),
      34 =>
      array(
        'maphuongxa' => 40713035,
        'tenphuongxa' => 'Xã Lệ Thủy',
      ),
      35 =>
      array(
        'maphuongxa' => 40713036,
        'tenphuongxa' => 'Xã Cam Hồng',
      ),
      36 =>
      array(
        'maphuongxa' => 40713037,
        'tenphuongxa' => 'Xã Sen Ngư',
      ),
      37 =>
      array(
        'maphuongxa' => 40713038,
        'tenphuongxa' => 'Xã Tân Mỹ',
      ),
      38 =>
      array(
        'maphuongxa' => 40713039,
        'tenphuongxa' => 'Xã Trường Phú',
      ),
      39 =>
      array(
        'maphuongxa' => 40713040,
        'tenphuongxa' => 'Xã Lệ Ninh',
      ),
      40 =>
      array(
        'maphuongxa' => 40713041,
        'tenphuongxa' => 'Xã Kim Ngân',
      ),
      41 =>
      array(
        'maphuongxa' => 40901042,
        'tenphuongxa' => 'Phường Đông Hà',
      ),
      42 =>
      array(
        'maphuongxa' => 40901043,
        'tenphuongxa' => 'Phường Nam Đông Hà',
      ),
      43 =>
      array(
        'maphuongxa' => 40903044,
        'tenphuongxa' => 'Phường Quảng Trị',
      ),
      44 =>
      array(
        'maphuongxa' => 40905045,
        'tenphuongxa' => 'Xã Vĩnh Linh',
      ),
      45 =>
      array(
        'maphuongxa' => 40905046,
        'tenphuongxa' => 'Xã Cửa Tùng',
      ),
      46 =>
      array(
        'maphuongxa' => 40905047,
        'tenphuongxa' => 'Xã Vĩnh Hoàng',
      ),
      47 =>
      array(
        'maphuongxa' => 40905048,
        'tenphuongxa' => 'Xã Vĩnh Thủy',
      ),
      48 =>
      array(
        'maphuongxa' => 40905049,
        'tenphuongxa' => 'Xã Bến Quan',
      ),
      49 =>
      array(
        'maphuongxa' => 40907050,
        'tenphuongxa' => 'Xã Cồn Tiên',
      ),
      50 =>
      array(
        'maphuongxa' => 40907051,
        'tenphuongxa' => 'Xã Cửa Việt',
      ),
      51 =>
      array(
        'maphuongxa' => 40907052,
        'tenphuongxa' => 'Xã Gio Linh',
      ),
      52 =>
      array(
        'maphuongxa' => 40907053,
        'tenphuongxa' => 'Xã Bến Hải',
      ),
      53 =>
      array(
        'maphuongxa' => 40915054,
        'tenphuongxa' => 'Xã Hướng Lập',
      ),
      54 =>
      array(
        'maphuongxa' => 40915055,
        'tenphuongxa' => 'Xã Hướng Phùng',
      ),
      55 =>
      array(
        'maphuongxa' => 40915056,
        'tenphuongxa' => 'Xã Khe Sanh',
      ),
      56 =>
      array(
        'maphuongxa' => 40915057,
        'tenphuongxa' => 'Xã Tân Lập',
      ),
      57 =>
      array(
        'maphuongxa' => 40915058,
        'tenphuongxa' => 'Xã Lao Bảo',
      ),
      58 =>
      array(
        'maphuongxa' => 40915059,
        'tenphuongxa' => 'Xã Lìa',
      ),
      59 =>
      array(
        'maphuongxa' => 40915060,
        'tenphuongxa' => 'Xã A Dơi',
      ),
      60 =>
      array(
        'maphuongxa' => 40917061,
        'tenphuongxa' => 'Xã La Lay',
      ),
      61 =>
      array(
        'maphuongxa' => 40917062,
        'tenphuongxa' => 'Xã Tà Rụt',
      ),
      62 =>
      array(
        'maphuongxa' => 40917063,
        'tenphuongxa' => 'Xã Đakrông',
      ),
      63 =>
      array(
        'maphuongxa' => 40917064,
        'tenphuongxa' => 'Xã Ba Lòng',
      ),
      64 =>
      array(
        'maphuongxa' => 40917065,
        'tenphuongxa' => 'Xã Hướng Hiệp',
      ),
      65 =>
      array(
        'maphuongxa' => 40909066,
        'tenphuongxa' => 'Xã Cam Lộ',
      ),
      66 =>
      array(
        'maphuongxa' => 40909067,
        'tenphuongxa' => 'Xã Hiếu Giang',
      ),
      67 =>
      array(
        'maphuongxa' => 40911068,
        'tenphuongxa' => 'Xã Triệu Phong',
      ),
      68 =>
      array(
        'maphuongxa' => 40911069,
        'tenphuongxa' => 'Xã Ái Tử',
      ),
      69 =>
      array(
        'maphuongxa' => 40911070,
        'tenphuongxa' => 'Xã Triệu Bình',
      ),
      70 =>
      array(
        'maphuongxa' => 40911071,
        'tenphuongxa' => 'Xã Triệu Cơ',
      ),
      71 =>
      array(
        'maphuongxa' => 40911072,
        'tenphuongxa' => 'Xã Nam Cửa Việt',
      ),
      72 =>
      array(
        'maphuongxa' => 40913073,
        'tenphuongxa' => 'Xã Diên Sanh',
      ),
      73 =>
      array(
        'maphuongxa' => 40913074,
        'tenphuongxa' => 'Xã Mỹ Thủy',
      ),
      74 =>
      array(
        'maphuongxa' => 40913075,
        'tenphuongxa' => 'Xã Hải Lăng',
      ),
      75 =>
      array(
        'maphuongxa' => 40913076,
        'tenphuongxa' => 'Xã Vĩnh Định',
      ),
      76 =>
      array(
        'maphuongxa' => 40913077,
        'tenphuongxa' => 'Xã Nam Hải Lăng',
      ),
      77 =>
      array(
        'maphuongxa' => 40919078,
        'tenphuongxa' => 'Đặc khu Cồn Cỏ',
      ),
    ),
  ),
  19 =>
  array(
    'matinhBNV' => 20,
    'matinhTMS' => '411',
    'tentinhmoi' => 'Thành phố Huế',
    'phuongxa' =>
    array(
      0 =>
      array(
        'maphuongxa' => 41109001,
        'tenphuongxa' => 'Phường Thuận An',
      ),
      1 =>
      array(
        'maphuongxa' => 41119002,
        'tenphuongxa' => 'Phường Hóa Châu',
      ),
      2 =>
      array(
        'maphuongxa' => 41109003,
        'tenphuongxa' => 'Phường Mỹ Thượng',
      ),
      3 =>
      array(
        'maphuongxa' => 41101004,
        'tenphuongxa' => 'Phường Vỹ Dạ',
      ),
      4 =>
      array(
        'maphuongxa' => 41101005,
        'tenphuongxa' => 'Phường Thuận Hóa',
      ),
      5 =>
      array(
        'maphuongxa' => 41101006,
        'tenphuongxa' => 'Phường An Cựu',
      ),
      6 =>
      array(
        'maphuongxa' => 41101007,
        'tenphuongxa' => 'Phường Thủy Xuân',
      ),
      7 =>
      array(
        'maphuongxa' => 41119008,
        'tenphuongxa' => 'Phường Kim Long',
      ),
      8 =>
      array(
        'maphuongxa' => 41119009,
        'tenphuongxa' => 'Phường Hương An',
      ),
      9 =>
      array(
        'maphuongxa' => 41119010,
        'tenphuongxa' => 'Phường Phú Xuân',
      ),
      10 =>
      array(
        'maphuongxa' => 41107011,
        'tenphuongxa' => 'Phường Hương Trà',
      ),
      11 =>
      array(
        'maphuongxa' => 41107012,
        'tenphuongxa' => 'Phường Kim Trà',
      ),
      12 =>
      array(
        'maphuongxa' => 41111013,
        'tenphuongxa' => 'Phường Thanh Thủy',
      ),
      13 =>
      array(
        'maphuongxa' => 41111014,
        'tenphuongxa' => 'Phường Hương Thủy',
      ),
      14 =>
      array(
        'maphuongxa' => 41111015,
        'tenphuongxa' => 'Phường Phú Bài',
      ),
      15 =>
      array(
        'maphuongxa' => 41103016,
        'tenphuongxa' => 'Phường Phong Điền',
      ),
      16 =>
      array(
        'maphuongxa' => 41103017,
        'tenphuongxa' => 'Phường Phong Thái',
      ),
      17 =>
      array(
        'maphuongxa' => 41103018,
        'tenphuongxa' => 'Phường Phong Dinh',
      ),
      18 =>
      array(
        'maphuongxa' => 41103019,
        'tenphuongxa' => 'Phường Phong Phú',
      ),
      19 =>
      array(
        'maphuongxa' => 41105020,
        'tenphuongxa' => 'Phường Phong Quảng',
      ),
      20 =>
      array(
        'maphuongxa' => 41105021,
        'tenphuongxa' => 'Xã Đan Điền',
      ),
      21 =>
      array(
        'maphuongxa' => 41105022,
        'tenphuongxa' => 'Xã Quảng Điền',
      ),
      22 =>
      array(
        'maphuongxa' => 41109023,
        'tenphuongxa' => 'Xã Phú Vinh',
      ),
      23 =>
      array(
        'maphuongxa' => 41109024,
        'tenphuongxa' => 'Xã Phú Hồ',
      ),
      24 =>
      array(
        'maphuongxa' => 41109025,
        'tenphuongxa' => 'Xã Phú Vang',
      ),
      25 =>
      array(
        'maphuongxa' => 41113026,
        'tenphuongxa' => 'Xã Vinh Lộc',
      ),
      26 =>
      array(
        'maphuongxa' => 41113027,
        'tenphuongxa' => 'Xã Hưng Lộc',
      ),
      27 =>
      array(
        'maphuongxa' => 41113028,
        'tenphuongxa' => 'Xã Lộc An',
      ),
      28 =>
      array(
        'maphuongxa' => 41113029,
        'tenphuongxa' => 'Xã Phú Lộc',
      ),
      29 =>
      array(
        'maphuongxa' => 41113030,
        'tenphuongxa' => 'Xã Chân Mây – Lăng Cô',
      ),
      30 =>
      array(
        'maphuongxa' => 41113031,
        'tenphuongxa' => 'Xã Long Quảng',
      ),
      31 =>
      array(
        'maphuongxa' => 41113032,
        'tenphuongxa' => 'Xã Nam Đông',
      ),
      32 =>
      array(
        'maphuongxa' => 41113033,
        'tenphuongxa' => 'Xã Khe Tre',
      ),
      33 =>
      array(
        'maphuongxa' => 41107034,
        'tenphuongxa' => 'Xã Bình Điền',
      ),
      34 =>
      array(
        'maphuongxa' => 41115035,
        'tenphuongxa' => 'Xã A Lưới 1',
      ),
      35 =>
      array(
        'maphuongxa' => 41115036,
        'tenphuongxa' => 'Xã A Lưới 2',
      ),
      36 =>
      array(
        'maphuongxa' => 41115037,
        'tenphuongxa' => 'Xã A Lưới 3',
      ),
      37 =>
      array(
        'maphuongxa' => 41115038,
        'tenphuongxa' => 'Xã A Lưới 4',
      ),
      38 =>
      array(
        'maphuongxa' => 41115039,
        'tenphuongxa' => 'Xã A Lưới 5',
      ),
      39 =>
      array(
        'maphuongxa' => 41101040,
        'tenphuongxa' => 'Phường Dương Nỗ',
      ),
    ),
  ),
  20 =>
  array(
    'matinhBNV' => 21,
    'matinhTMS' => '501',
    'tentinhmoi' => 'Thành Phố Đà Nẵng',
    'phuongxa' =>
    array(
      0 =>
      array(
        'maphuongxa' => 50101001,
        'tenphuongxa' => 'Phường Hải Châu',
      ),
      1 =>
      array(
        'maphuongxa' => 50101002,
        'tenphuongxa' => 'Phường Hoà Cường',
      ),
      2 =>
      array(
        'maphuongxa' => 50103003,
        'tenphuongxa' => 'Phường Thanh Khê',
      ),
      3 =>
      array(
        'maphuongxa' => 50115004,
        'tenphuongxa' => 'Phường An Khê',
      ),
      4 =>
      array(
        'maphuongxa' => 50105005,
        'tenphuongxa' => 'Phường An Hải',
      ),
      5 =>
      array(
        'maphuongxa' => 50105006,
        'tenphuongxa' => 'Phường Sơn Trà',
      ),
      6 =>
      array(
        'maphuongxa' => 50107007,
        'tenphuongxa' => 'Phường Ngũ Hành Sơn',
      ),
      7 =>
      array(
        'maphuongxa' => 50109008,
        'tenphuongxa' => 'Phường Hoà Khánh',
      ),
      8 =>
      array(
        'maphuongxa' => 50109009,
        'tenphuongxa' => 'Phường Hải Vân',
      ),
      9 =>
      array(
        'maphuongxa' => 50109010,
        'tenphuongxa' => 'Phường Liên Chiểu',
      ),
      10 =>
      array(
        'maphuongxa' => 50115011,
        'tenphuongxa' => 'Phường Cẩm Lệ',
      ),
      11 =>
      array(
        'maphuongxa' => 50111012,
        'tenphuongxa' => 'Phường Hoà Xuân',
      ),
      12 =>
      array(
        'maphuongxa' => 50111013,
        'tenphuongxa' => 'Xã Hoà Vang',
      ),
      13 =>
      array(
        'maphuongxa' => 50111014,
        'tenphuongxa' => 'Xã Hoà Tiến',
      ),
      14 =>
      array(
        'maphuongxa' => 50111015,
        'tenphuongxa' => 'Xã Bà Nà',
      ),
      15 =>
      array(
        'maphuongxa' => 50113016,
        'tenphuongxa' => 'Đặc khu Hoàng Sa',
      ),
      16 =>
      array(
        'maphuongxa' => 50325017,
        'tenphuongxa' => 'Xã Núi Thành',
      ),
      17 =>
      array(
        'maphuongxa' => 50325018,
        'tenphuongxa' => 'Xã Tam Mỹ',
      ),
      18 =>
      array(
        'maphuongxa' => 50325019,
        'tenphuongxa' => 'Xã Tam Anh',
      ),
      19 =>
      array(
        'maphuongxa' => 50325020,
        'tenphuongxa' => 'Xã Đức Phú',
      ),
      20 =>
      array(
        'maphuongxa' => 50325021,
        'tenphuongxa' => 'Xã Tam Xuân',
      ),
      21 =>
      array(
        'maphuongxa' => 50325022,
        'tenphuongxa' => 'Xã Tam Hải',
      ),
      22 =>
      array(
        'maphuongxa' => 50301023,
        'tenphuongxa' => 'Phường Tam Kỳ',
      ),
      23 =>
      array(
        'maphuongxa' => 50301024,
        'tenphuongxa' => 'Phường Quảng Phú',
      ),
      24 =>
      array(
        'maphuongxa' => 50301025,
        'tenphuongxa' => 'Phường Hương Trà',
      ),
      25 =>
      array(
        'maphuongxa' => 50301026,
        'tenphuongxa' => 'Phường Bàn Thạch',
      ),
      26 =>
      array(
        'maphuongxa' => 50302027,
        'tenphuongxa' => 'Xã Tây Hồ',
      ),
      27 =>
      array(
        'maphuongxa' => 50302028,
        'tenphuongxa' => 'Xã Chiên Đàn',
      ),
      28 =>
      array(
        'maphuongxa' => 50302029,
        'tenphuongxa' => 'Xã Phú Ninh',
      ),
      29 =>
      array(
        'maphuongxa' => 50321030,
        'tenphuongxa' => 'Xã Lãnh Ngọc',
      ),
      30 =>
      array(
        'maphuongxa' => 50321031,
        'tenphuongxa' => 'Xã Tiên Phước',
      ),
      31 =>
      array(
        'maphuongxa' => 50321032,
        'tenphuongxa' => 'Xã Thạnh Bình',
      ),
      32 =>
      array(
        'maphuongxa' => 50321033,
        'tenphuongxa' => 'Xã Sơn Cẩm Hà',
      ),
      33 =>
      array(
        'maphuongxa' => 50327034,
        'tenphuongxa' => 'Xã Trà Liên',
      ),
      34 =>
      array(
        'maphuongxa' => 50327035,
        'tenphuongxa' => 'Xã Trà Giáp',
      ),
      35 =>
      array(
        'maphuongxa' => 50327036,
        'tenphuongxa' => 'Xã Trà Tân',
      ),
      36 =>
      array(
        'maphuongxa' => 50327037,
        'tenphuongxa' => 'Xã Trà Đốc',
      ),
      37 =>
      array(
        'maphuongxa' => 50327038,
        'tenphuongxa' => 'Xã Trà My',
      ),
      38 =>
      array(
        'maphuongxa' => 50329039,
        'tenphuongxa' => 'Xã Nam Trà My',
      ),
      39 =>
      array(
        'maphuongxa' => 50329040,
        'tenphuongxa' => 'Xã Trà Tập',
      ),
      40 =>
      array(
        'maphuongxa' => 50329041,
        'tenphuongxa' => 'Xã Trà Vân',
      ),
      41 =>
      array(
        'maphuongxa' => 50329042,
        'tenphuongxa' => 'Xã Trà Linh',
      ),
      42 =>
      array(
        'maphuongxa' => 50329043,
        'tenphuongxa' => 'Xã Trà Leng',
      ),
      43 =>
      array(
        'maphuongxa' => 50315044,
        'tenphuongxa' => 'Xã Thăng Bình',
      ),
      44 =>
      array(
        'maphuongxa' => 50315045,
        'tenphuongxa' => 'Xã Thăng An',
      ),
      45 =>
      array(
        'maphuongxa' => 50315046,
        'tenphuongxa' => 'Xã Thăng Trường',
      ),
      46 =>
      array(
        'maphuongxa' => 50315047,
        'tenphuongxa' => 'Xã Thăng Điền',
      ),
      47 =>
      array(
        'maphuongxa' => 50315048,
        'tenphuongxa' => 'Xã Thăng Phú',
      ),
      48 =>
      array(
        'maphuongxa' => 50315049,
        'tenphuongxa' => 'Xã Đồng Dương',
      ),
      49 =>
      array(
        'maphuongxa' => 50317050,
        'tenphuongxa' => 'Xã Quế Sơn Trung',
      ),
      50 =>
      array(
        'maphuongxa' => 50317051,
        'tenphuongxa' => 'Xã Quế Sơn',
      ),
      51 =>
      array(
        'maphuongxa' => 50317052,
        'tenphuongxa' => 'Xã Xuân Phú',
      ),
      52 =>
      array(
        'maphuongxa' => 50317053,
        'tenphuongxa' => 'Xã Nông Sơn',
      ),
      53 =>
      array(
        'maphuongxa' => 50317054,
        'tenphuongxa' => 'Xã Quế Phước',
      ),
      54 =>
      array(
        'maphuongxa' => 50311055,
        'tenphuongxa' => 'Xã Duy Nghĩa',
      ),
      55 =>
      array(
        'maphuongxa' => 50311056,
        'tenphuongxa' => 'Xã Nam Phước',
      ),
      56 =>
      array(
        'maphuongxa' => 50311057,
        'tenphuongxa' => 'Xã Duy Xuyên',
      ),
      57 =>
      array(
        'maphuongxa' => 50311058,
        'tenphuongxa' => 'Xã Thu Bồn',
      ),
      58 =>
      array(
        'maphuongxa' => 50309059,
        'tenphuongxa' => 'Phường Điện Bàn',
      ),
      59 =>
      array(
        'maphuongxa' => 50309060,
        'tenphuongxa' => 'Phường Điện Bàn Đông',
      ),
      60 =>
      array(
        'maphuongxa' => 50309061,
        'tenphuongxa' => 'Phường An Thắng',
      ),
      61 =>
      array(
        'maphuongxa' => 50309062,
        'tenphuongxa' => 'Phường Điện Bàn Bắc',
      ),
      62 =>
      array(
        'maphuongxa' => 50309063,
        'tenphuongxa' => 'Xã Điện Bàn Tây',
      ),
      63 =>
      array(
        'maphuongxa' => 50309064,
        'tenphuongxa' => 'Xã Gò Nổi',
      ),
      64 =>
      array(
        'maphuongxa' => 50303065,
        'tenphuongxa' => 'Phường Hội An',
      ),
      65 =>
      array(
        'maphuongxa' => 50303066,
        'tenphuongxa' => 'Phường Hội An Đông',
      ),
      66 =>
      array(
        'maphuongxa' => 50303067,
        'tenphuongxa' => 'Phường Hội An Tây',
      ),
      67 =>
      array(
        'maphuongxa' => 50303068,
        'tenphuongxa' => 'Xã Tân Hiệp',
      ),
      68 =>
      array(
        'maphuongxa' => 50307069,
        'tenphuongxa' => 'Xã Đại Lộc',
      ),
      69 =>
      array(
        'maphuongxa' => 50307070,
        'tenphuongxa' => 'Xã Hà Nha',
      ),
      70 =>
      array(
        'maphuongxa' => 50307071,
        'tenphuongxa' => 'Xã Thượng Đức',
      ),
      71 =>
      array(
        'maphuongxa' => 50307072,
        'tenphuongxa' => 'Xã Vu Gia',
      ),
      72 =>
      array(
        'maphuongxa' => 50307073,
        'tenphuongxa' => 'Xã Phú Thuận',
      ),
      73 =>
      array(
        'maphuongxa' => 50313074,
        'tenphuongxa' => 'Xã Thạnh Mỹ',
      ),
      74 =>
      array(
        'maphuongxa' => 50313075,
        'tenphuongxa' => 'Xã Bến Giằng',
      ),
      75 =>
      array(
        'maphuongxa' => 50313076,
        'tenphuongxa' => 'Xã Nam Giang',
      ),
      76 =>
      array(
        'maphuongxa' => 50313077,
        'tenphuongxa' => 'Xã Đắc Pring',
      ),
      77 =>
      array(
        'maphuongxa' => 50313078,
        'tenphuongxa' => 'Xã La Dêê',
      ),
      78 =>
      array(
        'maphuongxa' => 50313079,
        'tenphuongxa' => 'Xã La Êê',
      ),
      79 =>
      array(
        'maphuongxa' => 50305080,
        'tenphuongxa' => 'Xã Sông Vàng',
      ),
      80 =>
      array(
        'maphuongxa' => 50305081,
        'tenphuongxa' => 'Xã Sông Kôn',
      ),
      81 =>
      array(
        'maphuongxa' => 50305082,
        'tenphuongxa' => 'Xã Đông Giang',
      ),
      82 =>
      array(
        'maphuongxa' => 50305083,
        'tenphuongxa' => 'Xã Bến Hiên',
      ),
      83 =>
      array(
        'maphuongxa' => 50304084,
        'tenphuongxa' => 'Xã Avương',
      ),
      84 =>
      array(
        'maphuongxa' => 50304085,
        'tenphuongxa' => 'Xã Tây Giang',
      ),
      85 =>
      array(
        'maphuongxa' => 50304086,
        'tenphuongxa' => 'Xã Hùng Sơn',
      ),
      86 =>
      array(
        'maphuongxa' => 50319087,
        'tenphuongxa' => 'Xã Hiệp Đức',
      ),
      87 =>
      array(
        'maphuongxa' => 50319088,
        'tenphuongxa' => 'Xã Việt An',
      ),
      88 =>
      array(
        'maphuongxa' => 50319089,
        'tenphuongxa' => 'Xã Phước Trà',
      ),
      89 =>
      array(
        'maphuongxa' => 50323090,
        'tenphuongxa' => 'Xã Khâm Đức',
      ),
      90 =>
      array(
        'maphuongxa' => 50323091,
        'tenphuongxa' => 'Xã Phước Năng',
      ),
      91 =>
      array(
        'maphuongxa' => 50323092,
        'tenphuongxa' => 'Xã Phước Chánh',
      ),
      92 =>
      array(
        'maphuongxa' => 50323093,
        'tenphuongxa' => 'Xã Phước Thành',
      ),
      93 =>
      array(
        'maphuongxa' => 50323094,
        'tenphuongxa' => 'Xã Phước Hiệp',
      ),
    ),
  ),
  21 =>
  array(
    'matinhBNV' => 22,
    'matinhTMS' => '505',
    'tentinhmoi' => 'Tỉnh Quảng Ngãi',
    'phuongxa' =>
    array(
      0 =>
      array(
        'maphuongxa' => 50501001,
        'tenphuongxa' => 'Xã Tịnh Khê',
      ),
      1 =>
      array(
        'maphuongxa' => 50501002,
        'tenphuongxa' => 'Phường Trương Quang Trọng',
      ),
      2 =>
      array(
        'maphuongxa' => 50501003,
        'tenphuongxa' => 'Xã An Phú',
      ),
      3 =>
      array(
        'maphuongxa' => 50501004,
        'tenphuongxa' => 'Phường Cẩm Thành',
      ),
      4 =>
      array(
        'maphuongxa' => 50501005,
        'tenphuongxa' => 'Phường Nghĩa Lộ',
      ),
      5 =>
      array(
        'maphuongxa' => 50523006,
        'tenphuongxa' => 'Phường Trà Câu',
      ),
      6 =>
      array(
        'maphuongxa' => 50523007,
        'tenphuongxa' => 'Xã Nguyễn Nghiêm',
      ),
      7 =>
      array(
        'maphuongxa' => 50523008,
        'tenphuongxa' => 'Phường Đức Phổ',
      ),
      8 =>
      array(
        'maphuongxa' => 50523009,
        'tenphuongxa' => 'Xã Khánh Cường',
      ),
      9 =>
      array(
        'maphuongxa' => 50523010,
        'tenphuongxa' => 'Phường Sa Huỳnh',
      ),
      10 =>
      array(
        'maphuongxa' => 50505011,
        'tenphuongxa' => 'Xã Bình Minh',
      ),
      11 =>
      array(
        'maphuongxa' => 50505012,
        'tenphuongxa' => 'Xã Bình Chương',
      ),
      12 =>
      array(
        'maphuongxa' => 50505013,
        'tenphuongxa' => 'Xã Bình Sơn',
      ),
      13 =>
      array(
        'maphuongxa' => 50505014,
        'tenphuongxa' => 'Xã Vạn Tường',
      ),
      14 =>
      array(
        'maphuongxa' => 50505015,
        'tenphuongxa' => 'Xã Đông Sơn',
      ),
      15 =>
      array(
        'maphuongxa' => 50509016,
        'tenphuongxa' => 'Xã Trường Giang',
      ),
      16 =>
      array(
        'maphuongxa' => 50509017,
        'tenphuongxa' => 'Xã Ba Gia',
      ),
      17 =>
      array(
        'maphuongxa' => 50509018,
        'tenphuongxa' => 'Xã Sơn Tịnh',
      ),
      18 =>
      array(
        'maphuongxa' => 50509019,
        'tenphuongxa' => 'Xã Thọ Phong',
      ),
      19 =>
      array(
        'maphuongxa' => 50515020,
        'tenphuongxa' => 'Xã Tư Nghĩa',
      ),
      20 =>
      array(
        'maphuongxa' => 50515021,
        'tenphuongxa' => 'Xã Vệ Giang',
      ),
      21 =>
      array(
        'maphuongxa' => 50515022,
        'tenphuongxa' => 'Xã Nghĩa Giang',
      ),
      22 =>
      array(
        'maphuongxa' => 50515023,
        'tenphuongxa' => 'Xã Trà Giang',
      ),
      23 =>
      array(
        'maphuongxa' => 50517024,
        'tenphuongxa' => 'Xã Nghĩa Hành',
      ),
      24 =>
      array(
        'maphuongxa' => 50517025,
        'tenphuongxa' => 'Xã Đình Cương',
      ),
      25 =>
      array(
        'maphuongxa' => 50517026,
        'tenphuongxa' => 'Xã Thiện Tín',
      ),
      26 =>
      array(
        'maphuongxa' => 50517027,
        'tenphuongxa' => 'Xã Phước Giang',
      ),
      27 =>
      array(
        'maphuongxa' => 50521028,
        'tenphuongxa' => 'Xã Long Phụng',
      ),
      28 =>
      array(
        'maphuongxa' => 50521029,
        'tenphuongxa' => 'Xã Mỏ Cày',
      ),
      29 =>
      array(
        'maphuongxa' => 50521030,
        'tenphuongxa' => 'Xã Mộ Đức',
      ),
      30 =>
      array(
        'maphuongxa' => 50521031,
        'tenphuongxa' => 'Xã Lân Phong',
      ),
      31 =>
      array(
        'maphuongxa' => 50507032,
        'tenphuongxa' => 'Xã Trà Bồng',
      ),
      32 =>
      array(
        'maphuongxa' => 50507033,
        'tenphuongxa' => 'Xã Đông Trà Bồng',
      ),
      33 =>
      array(
        'maphuongxa' => 50507034,
        'tenphuongxa' => 'Xã Tây Trà',
      ),
      34 =>
      array(
        'maphuongxa' => 50507035,
        'tenphuongxa' => 'Xã Thanh Bồng',
      ),
      35 =>
      array(
        'maphuongxa' => 50507036,
        'tenphuongxa' => 'Xã Cà Đam',
      ),
      36 =>
      array(
        'maphuongxa' => 50507037,
        'tenphuongxa' => 'Xã Tây Trà Bồng',
      ),
      37 =>
      array(
        'maphuongxa' => 50513038,
        'tenphuongxa' => 'Xã Sơn Hạ',
      ),
      38 =>
      array(
        'maphuongxa' => 50513039,
        'tenphuongxa' => 'Xã Sơn Linh',
      ),
      39 =>
      array(
        'maphuongxa' => 50513040,
        'tenphuongxa' => 'Xã Sơn Hà',
      ),
      40 =>
      array(
        'maphuongxa' => 50513041,
        'tenphuongxa' => 'Xã Sơn Thủy',
      ),
      41 =>
      array(
        'maphuongxa' => 50513042,
        'tenphuongxa' => 'Xã Sơn Kỳ',
      ),
      42 =>
      array(
        'maphuongxa' => 50511043,
        'tenphuongxa' => 'Xã Sơn Tây',
      ),
      43 =>
      array(
        'maphuongxa' => 50511044,
        'tenphuongxa' => 'Xã Sơn Tây Thượng',
      ),
      44 =>
      array(
        'maphuongxa' => 50511045,
        'tenphuongxa' => 'Xã Sơn Tây Hạ',
      ),
      45 =>
      array(
        'maphuongxa' => 50519046,
        'tenphuongxa' => 'Xã Minh Long',
      ),
      46 =>
      array(
        'maphuongxa' => 50519047,
        'tenphuongxa' => 'Xã Sơn Mai',
      ),
      47 =>
      array(
        'maphuongxa' => 50525048,
        'tenphuongxa' => 'Xã Ba Vì',
      ),
      48 =>
      array(
        'maphuongxa' => 50525049,
        'tenphuongxa' => 'Xã Ba Tô',
      ),
      49 =>
      array(
        'maphuongxa' => 50525050,
        'tenphuongxa' => 'Xã Ba Dinh',
      ),
      50 =>
      array(
        'maphuongxa' => 50525051,
        'tenphuongxa' => 'Xã Ba Tơ',
      ),
      51 =>
      array(
        'maphuongxa' => 50525052,
        'tenphuongxa' => 'Xã Ba Vinh',
      ),
      52 =>
      array(
        'maphuongxa' => 50525053,
        'tenphuongxa' => 'Xã Ba Động',
      ),
      53 =>
      array(
        'maphuongxa' => 50525054,
        'tenphuongxa' => 'Xã Đặng Thùy Trâm',
      ),
      54 =>
      array(
        'maphuongxa' => 50525055,
        'tenphuongxa' => 'Xã Ba Xa',
      ),
      55 =>
      array(
        'maphuongxa' => 50503056,
        'tenphuongxa' => 'Đặc khu Lý Sơn',
      ),
      56 =>
      array(
        'maphuongxa' => 60101057,
        'tenphuongxa' => 'Phường Kon Tum',
      ),
      57 =>
      array(
        'maphuongxa' => 60101058,
        'tenphuongxa' => 'Phường Đăk Cấm',
      ),
      58 =>
      array(
        'maphuongxa' => 60101059,
        'tenphuongxa' => 'Phường Đăk BLa',
      ),
      59 =>
      array(
        'maphuongxa' => 60101060,
        'tenphuongxa' => 'Xã Ngọk Bay',
      ),
      60 =>
      array(
        'maphuongxa' => 60101061,
        'tenphuongxa' => 'Xã Ia Chim',
      ),
      61 =>
      array(
        'maphuongxa' => 60101062,
        'tenphuongxa' => 'Xã Đăk Rơ Wa',
      ),
      62 =>
      array(
        'maphuongxa' => 60111063,
        'tenphuongxa' => 'Xã Đăk Pxi',
      ),
      63 =>
      array(
        'maphuongxa' => 60111064,
        'tenphuongxa' => 'Xã Đăk Mar',
      ),
      64 =>
      array(
        'maphuongxa' => 60111065,
        'tenphuongxa' => 'Xã Đăk Ui',
      ),
      65 =>
      array(
        'maphuongxa' => 60111066,
        'tenphuongxa' => 'Xã Ngọk Réo',
      ),
      66 =>
      array(
        'maphuongxa' => 60111067,
        'tenphuongxa' => 'Xã Đăk Hà',
      ),
      67 =>
      array(
        'maphuongxa' => 60107068,
        'tenphuongxa' => 'Xã Ngọk Tụ',
      ),
      68 =>
      array(
        'maphuongxa' => 60107069,
        'tenphuongxa' => 'Xã Đăk Tô',
      ),
      69 =>
      array(
        'maphuongxa' => 60107070,
        'tenphuongxa' => 'Xã Kon Đào',
      ),
      70 =>
      array(
        'maphuongxa' => 60115071,
        'tenphuongxa' => 'Xã Đăk Sao',
      ),
      71 =>
      array(
        'maphuongxa' => 60115072,
        'tenphuongxa' => 'Xã Đăk Tờ Kan',
      ),
      72 =>
      array(
        'maphuongxa' => 60115073,
        'tenphuongxa' => 'Xã Tu Mơ Rông',
      ),
      73 =>
      array(
        'maphuongxa' => 60115074,
        'tenphuongxa' => 'Xã Măng Ri',
      ),
      74 =>
      array(
        'maphuongxa' => 60105075,
        'tenphuongxa' => 'Xã Bờ Y',
      ),
      75 =>
      array(
        'maphuongxa' => 60105076,
        'tenphuongxa' => 'Xã Sa Loong',
      ),
      76 =>
      array(
        'maphuongxa' => 60105077,
        'tenphuongxa' => 'Xã Dục Nông',
      ),
      77 =>
      array(
        'maphuongxa' => 60103078,
        'tenphuongxa' => 'Xã Xốp',
      ),
      78 =>
      array(
        'maphuongxa' => 60103079,
        'tenphuongxa' => 'Xã Ngọc Linh',
      ),
      79 =>
      array(
        'maphuongxa' => 60103080,
        'tenphuongxa' => 'Xã Đăk Plô',
      ),
      80 =>
      array(
        'maphuongxa' => 60103081,
        'tenphuongxa' => 'Xã Đăk Pék',
      ),
      81 =>
      array(
        'maphuongxa' => 60103082,
        'tenphuongxa' => 'Xã Đăk Môn',
      ),
      82 =>
      array(
        'maphuongxa' => 60113083,
        'tenphuongxa' => 'Xã Sa Thầy',
      ),
      83 =>
      array(
        'maphuongxa' => 60113084,
        'tenphuongxa' => 'Xã Sa Bình',
      ),
      84 =>
      array(
        'maphuongxa' => 60113085,
        'tenphuongxa' => 'Xã Ya Ly',
      ),
      85 =>
      array(
        'maphuongxa' => 60114086,
        'tenphuongxa' => 'Xã Ia Tơi',
      ),
      86 =>
      array(
        'maphuongxa' => 60108087,
        'tenphuongxa' => 'Xã Đăk Kôi',
      ),
      87 =>
      array(
        'maphuongxa' => 60108088,
        'tenphuongxa' => 'Xã Kon Braih',
      ),
      88 =>
      array(
        'maphuongxa' => 60108089,
        'tenphuongxa' => 'Xã Đăk Rve',
      ),
      89 =>
      array(
        'maphuongxa' => 60109090,
        'tenphuongxa' => 'Xã Măng Đen',
      ),
      90 =>
      array(
        'maphuongxa' => 60109091,
        'tenphuongxa' => 'Xã Măng Bút',
      ),
      91 =>
      array(
        'maphuongxa' => 60109092,
        'tenphuongxa' => 'Xã Kon Plông',
      ),
      92 =>
      array(
        'maphuongxa' => 60103093,
        'tenphuongxa' => 'Xã Đăk Long',
      ),
      93 =>
      array(
        'maphuongxa' => 60113094,
        'tenphuongxa' => 'Xã Rờ Kơi',
      ),
      94 =>
      array(
        'maphuongxa' => 60113095,
        'tenphuongxa' => 'Xã Mô Rai',
      ),
      95 =>
      array(
        'maphuongxa' => 60114096,
        'tenphuongxa' => 'Xã Ia Đal',
      ),
    ),
  ),
  22 =>
  array(
    'matinhBNV' => 23,
    'matinhTMS' => 511,
    'tentinhmoi' => 'Tỉnh Khánh Hòa',
    'phuongxa' =>
    array(
      0 =>
      array(
        'maphuongxa' => 51101001,
        'tenphuongxa' => 'Phường Nha Trang',
      ),
      1 =>
      array(
        'maphuongxa' => 51101002,
        'tenphuongxa' => 'Phường Bắc Nha Trang',
      ),
      2 =>
      array(
        'maphuongxa' => 51101003,
        'tenphuongxa' => 'Phường Tây Nha Trang',
      ),
      3 =>
      array(
        'maphuongxa' => 51101004,
        'tenphuongxa' => 'Phường Nam Nha Trang',
      ),
      4 =>
      array(
        'maphuongxa' => 51109005,
        'tenphuongxa' => 'Phường Bắc Cam Ranh',
      ),
      5 =>
      array(
        'maphuongxa' => 51109006,
        'tenphuongxa' => 'Phường Cam Ranh',
      ),
      6 =>
      array(
        'maphuongxa' => 51109007,
        'tenphuongxa' => 'Phường Cam Linh',
      ),
      7 =>
      array(
        'maphuongxa' => 51109008,
        'tenphuongxa' => 'Phường Ba Ngòi',
      ),
      8 =>
      array(
        'maphuongxa' => 51109009,
        'tenphuongxa' => 'Xã Nam Cam Ranh',
      ),
      9 =>
      array(
        'maphuongxa' => 51105010,
        'tenphuongxa' => 'Xã Bắc Ninh Hoà',
      ),
      10 =>
      array(
        'maphuongxa' => 51105011,
        'tenphuongxa' => 'Phường Ninh Hoà',
      ),
      11 =>
      array(
        'maphuongxa' => 51105012,
        'tenphuongxa' => 'Xã Tân Định',
      ),
      12 =>
      array(
        'maphuongxa' => 51105013,
        'tenphuongxa' => 'Phường Đông Ninh Hoà',
      ),
      13 =>
      array(
        'maphuongxa' => 51105014,
        'tenphuongxa' => 'Phường Hoà Thắng',
      ),
      14 =>
      array(
        'maphuongxa' => 51105015,
        'tenphuongxa' => 'Xã Nam Ninh Hoà',
      ),
      15 =>
      array(
        'maphuongxa' => 51105016,
        'tenphuongxa' => 'Xã Tây Ninh Hoà',
      ),
      16 =>
      array(
        'maphuongxa' => 51105017,
        'tenphuongxa' => 'Xã Hoà Trí',
      ),
      17 =>
      array(
        'maphuongxa' => 51103018,
        'tenphuongxa' => 'Xã Đại Lãnh',
      ),
      18 =>
      array(
        'maphuongxa' => 51103019,
        'tenphuongxa' => 'Xã Tu Bông',
      ),
      19 =>
      array(
        'maphuongxa' => 51103020,
        'tenphuongxa' => 'Xã Vạn Thắng',
      ),
      20 =>
      array(
        'maphuongxa' => 51103021,
        'tenphuongxa' => 'Xã Vạn Ninh',
      ),
      21 =>
      array(
        'maphuongxa' => 51103022,
        'tenphuongxa' => 'Xã Vạn Hưng',
      ),
      22 =>
      array(
        'maphuongxa' => 51107023,
        'tenphuongxa' => 'Xã Diên Khánh',
      ),
      23 =>
      array(
        'maphuongxa' => 51107024,
        'tenphuongxa' => 'Xã Diên Lạc',
      ),
      24 =>
      array(
        'maphuongxa' => 51107025,
        'tenphuongxa' => 'Xã Diên Điền',
      ),
      25 =>
      array(
        'maphuongxa' => 51107026,
        'tenphuongxa' => 'Xã Diên Lâm',
      ),
      26 =>
      array(
        'maphuongxa' => 51107027,
        'tenphuongxa' => 'Xã Diên Thọ',
      ),
      27 =>
      array(
        'maphuongxa' => 51107028,
        'tenphuongxa' => 'Xã Suối Hiệp',
      ),
      28 =>
      array(
        'maphuongxa' => 51117029,
        'tenphuongxa' => 'Xã Cam Lâm',
      ),
      29 =>
      array(
        'maphuongxa' => 51117030,
        'tenphuongxa' => 'Xã Suối Dầu',
      ),
      30 =>
      array(
        'maphuongxa' => 51117031,
        'tenphuongxa' => 'Xã Cam Hiệp',
      ),
      31 =>
      array(
        'maphuongxa' => 51117032,
        'tenphuongxa' => 'Xã Cam An',
      ),
      32 =>
      array(
        'maphuongxa' => 51111033,
        'tenphuongxa' => 'Xã Bắc Khánh Vĩnh',
      ),
      33 =>
      array(
        'maphuongxa' => 51111034,
        'tenphuongxa' => 'Xã Trung Khánh Vĩnh',
      ),
      34 =>
      array(
        'maphuongxa' => 51111035,
        'tenphuongxa' => 'Xã Tây Khánh Vĩnh',
      ),
      35 =>
      array(
        'maphuongxa' => 51111036,
        'tenphuongxa' => 'Xã Nam Khánh Vĩnh',
      ),
      36 =>
      array(
        'maphuongxa' => 51111037,
        'tenphuongxa' => 'Xã Khánh Vĩnh',
      ),
      37 =>
      array(
        'maphuongxa' => 51113038,
        'tenphuongxa' => 'Xã Khánh Sơn',
      ),
      38 =>
      array(
        'maphuongxa' => 51113039,
        'tenphuongxa' => 'Xã Tây Khánh Sơn',
      ),
      39 =>
      array(
        'maphuongxa' => 51113040,
        'tenphuongxa' => 'Xã Đông Khánh Sơn',
      ),
      40 =>
      array(
        'maphuongxa' => 51115041,
        'tenphuongxa' => 'Đặc khu Trường Sa',
      ),
      41 =>
      array(
        'maphuongxa' => 70501042,
        'tenphuongxa' => 'Phường Phan Rang',
      ),
      42 =>
      array(
        'maphuongxa' => 70501043,
        'tenphuongxa' => 'Phường Đông Hải',
      ),
      43 =>
      array(
        'maphuongxa' => 70505044,
        'tenphuongxa' => 'Phường Ninh Chử',
      ),
      44 =>
      array(
        'maphuongxa' => 70501045,
        'tenphuongxa' => 'Phường Bảo An',
      ),
      45 =>
      array(
        'maphuongxa' => 70501046,
        'tenphuongxa' => 'Phường Đô Vinh',
      ),
      46 =>
      array(
        'maphuongxa' => 70507047,
        'tenphuongxa' => 'Xã Ninh Phước',
      ),
      47 =>
      array(
        'maphuongxa' => 70507048,
        'tenphuongxa' => 'Xã Phước Hữu',
      ),
      48 =>
      array(
        'maphuongxa' => 70507049,
        'tenphuongxa' => 'Xã Phước Hậu',
      ),
      49 =>
      array(
        'maphuongxa' => 70513050,
        'tenphuongxa' => 'Xã Thuận Nam',
      ),
      50 =>
      array(
        'maphuongxa' => 70513051,
        'tenphuongxa' => 'Xã Cà Ná',
      ),
      51 =>
      array(
        'maphuongxa' => 70513052,
        'tenphuongxa' => 'Xã Phước Hà',
      ),
      52 =>
      array(
        'maphuongxa' => 70513053,
        'tenphuongxa' => 'Xã Phước Dinh',
      ),
      53 =>
      array(
        'maphuongxa' => 70505054,
        'tenphuongxa' => 'Xã Ninh Hải',
      ),
      54 =>
      array(
        'maphuongxa' => 70505055,
        'tenphuongxa' => 'Xã Xuân Hải',
      ),
      55 =>
      array(
        'maphuongxa' => 70505056,
        'tenphuongxa' => 'Xã Vĩnh Hải',
      ),
      56 =>
      array(
        'maphuongxa' => 70511057,
        'tenphuongxa' => 'Xã Thuận Bắc',
      ),
      57 =>
      array(
        'maphuongxa' => 70511058,
        'tenphuongxa' => 'Xã Công Hải',
      ),
      58 =>
      array(
        'maphuongxa' => 70503059,
        'tenphuongxa' => 'Xã Ninh Sơn',
      ),
      59 =>
      array(
        'maphuongxa' => 70503060,
        'tenphuongxa' => 'Xã Lâm Sơn',
      ),
      60 =>
      array(
        'maphuongxa' => 70503061,
        'tenphuongxa' => 'Xã Anh Dũng',
      ),
      61 =>
      array(
        'maphuongxa' => 70503062,
        'tenphuongxa' => 'Xã Mỹ Sơn',
      ),
      62 =>
      array(
        'maphuongxa' => 70509063,
        'tenphuongxa' => 'Xã Bác Ái Đông',
      ),
      63 =>
      array(
        'maphuongxa' => 70509064,
        'tenphuongxa' => 'Xã Bác Ái',
      ),
      64 =>
      array(
        'maphuongxa' => 70509065,
        'tenphuongxa' => 'Xã Bác Ái Tây',
      ),
    ),
  ),
  23 =>
  array(
    'matinhBNV' => 24,
    'matinhTMS' => '603',
    'tentinhmoi' => 'Tỉnh Gia Lai',
    'phuongxa' =>
    array(
      0 =>
      array(
        'maphuongxa' => 50701001,
        'tenphuongxa' => 'Phường Quy Nhơn',
      ),
      1 =>
      array(
        'maphuongxa' => 50701002,
        'tenphuongxa' => 'Phường Quy Nhơn Đông',
      ),
      2 =>
      array(
        'maphuongxa' => 50701003,
        'tenphuongxa' => 'Phường Quy Nhơn Tây',
      ),
      3 =>
      array(
        'maphuongxa' => 50701004,
        'tenphuongxa' => 'Phường Quy Nhơn Nam',
      ),
      4 =>
      array(
        'maphuongxa' => 50701005,
        'tenphuongxa' => 'Phường Quy Nhơn Bắc',
      ),
      5 =>
      array(
        'maphuongxa' => 50717006,
        'tenphuongxa' => 'Phường Bình Định',
      ),
      6 =>
      array(
        'maphuongxa' => 50717007,
        'tenphuongxa' => 'Phường An Nhơn',
      ),
      7 =>
      array(
        'maphuongxa' => 50717008,
        'tenphuongxa' => 'Phường An Nhơn Đông',
      ),
      8 =>
      array(
        'maphuongxa' => 50717009,
        'tenphuongxa' => 'Phường An Nhơn Nam',
      ),
      9 =>
      array(
        'maphuongxa' => 50717010,
        'tenphuongxa' => 'Phường An Nhơn Bắc',
      ),
      10 =>
      array(
        'maphuongxa' => 50717011,
        'tenphuongxa' => 'Xã An Nhơn Tây',
      ),
      11 =>
      array(
        'maphuongxa' => 50705012,
        'tenphuongxa' => 'Phường Bồng Sơn',
      ),
      12 =>
      array(
        'maphuongxa' => 50705013,
        'tenphuongxa' => 'Phường Hoài Nhơn',
      ),
      13 =>
      array(
        'maphuongxa' => 50705014,
        'tenphuongxa' => 'Phường Tam Quan',
      ),
      14 =>
      array(
        'maphuongxa' => 50705015,
        'tenphuongxa' => 'Phường Hoài Nhơn Đông',
      ),
      15 =>
      array(
        'maphuongxa' => 50705016,
        'tenphuongxa' => 'Phường Hoài Nhơn Tây',
      ),
      16 =>
      array(
        'maphuongxa' => 50705017,
        'tenphuongxa' => 'Phường Hoài Nhơn Nam',
      ),
      17 =>
      array(
        'maphuongxa' => 50705018,
        'tenphuongxa' => 'Phường Hoài Nhơn Bắc',
      ),
      18 =>
      array(
        'maphuongxa' => 50713019,
        'tenphuongxa' => 'Xã Phù Cát',
      ),
      19 =>
      array(
        'maphuongxa' => 50713020,
        'tenphuongxa' => 'Xã Xuân An',
      ),
      20 =>
      array(
        'maphuongxa' => 50713021,
        'tenphuongxa' => 'Xã Ngô Mây',
      ),
      21 =>
      array(
        'maphuongxa' => 50713022,
        'tenphuongxa' => 'Xã Cát Tiến',
      ),
      22 =>
      array(
        'maphuongxa' => 50713023,
        'tenphuongxa' => 'Xã Đề Gi',
      ),
      23 =>
      array(
        'maphuongxa' => 50713024,
        'tenphuongxa' => 'Xã Hoà Hội',
      ),
      24 =>
      array(
        'maphuongxa' => 50713025,
        'tenphuongxa' => 'Xã Hội Sơn',
      ),
      25 =>
      array(
        'maphuongxa' => 50709026,
        'tenphuongxa' => 'Xã Phù Mỹ',
      ),
      26 =>
      array(
        'maphuongxa' => 50709027,
        'tenphuongxa' => 'Xã An Lương',
      ),
      27 =>
      array(
        'maphuongxa' => 50709028,
        'tenphuongxa' => 'Xã Bình Dương',
      ),
      28 =>
      array(
        'maphuongxa' => 50709029,
        'tenphuongxa' => 'Xã Phù Mỹ Đông',
      ),
      29 =>
      array(
        'maphuongxa' => 50709030,
        'tenphuongxa' => 'Xã Phù Mỹ Tây',
      ),
      30 =>
      array(
        'maphuongxa' => 50709031,
        'tenphuongxa' => 'Xã Phù Mỹ Nam',
      ),
      31 =>
      array(
        'maphuongxa' => 50709032,
        'tenphuongxa' => 'Xã Phù Mỹ Bắc',
      ),
      32 =>
      array(
        'maphuongxa' => 50719033,
        'tenphuongxa' => 'Xã Tuy Phước',
      ),
      33 =>
      array(
        'maphuongxa' => 50719034,
        'tenphuongxa' => 'Xã Tuy Phước Đông',
      ),
      34 =>
      array(
        'maphuongxa' => 50719035,
        'tenphuongxa' => 'Xã Tuy Phước Tây',
      ),
      35 =>
      array(
        'maphuongxa' => 50719036,
        'tenphuongxa' => 'Xã Tuy Phước Bắc',
      ),
      36 =>
      array(
        'maphuongxa' => 50715037,
        'tenphuongxa' => 'Xã Tây Sơn',
      ),
      37 =>
      array(
        'maphuongxa' => 50715038,
        'tenphuongxa' => 'Xã Bình Khê',
      ),
      38 =>
      array(
        'maphuongxa' => 50715039,
        'tenphuongxa' => 'Xã Bình Phú',
      ),
      39 =>
      array(
        'maphuongxa' => 50715040,
        'tenphuongxa' => 'Xã Bình Hiệp',
      ),
      40 =>
      array(
        'maphuongxa' => 50715041,
        'tenphuongxa' => 'Xã Bình An',
      ),
      41 =>
      array(
        'maphuongxa' => 50707042,
        'tenphuongxa' => 'Xã Hoài Ân',
      ),
      42 =>
      array(
        'maphuongxa' => 50707043,
        'tenphuongxa' => 'Xã Ân Tường',
      ),
      43 =>
      array(
        'maphuongxa' => 50707044,
        'tenphuongxa' => 'Xã Kim Sơn',
      ),
      44 =>
      array(
        'maphuongxa' => 50707045,
        'tenphuongxa' => 'Xã Vạn Đức',
      ),
      45 =>
      array(
        'maphuongxa' => 50707046,
        'tenphuongxa' => 'Xã Ân Hảo',
      ),
      46 =>
      array(
        'maphuongxa' => 50721047,
        'tenphuongxa' => 'Xã Vân Canh',
      ),
      47 =>
      array(
        'maphuongxa' => 50721048,
        'tenphuongxa' => 'Xã Canh Vinh',
      ),
      48 =>
      array(
        'maphuongxa' => 50721049,
        'tenphuongxa' => 'Xã Canh Liên',
      ),
      49 =>
      array(
        'maphuongxa' => 50711050,
        'tenphuongxa' => 'Xã Vĩnh Thạnh',
      ),
      50 =>
      array(
        'maphuongxa' => 50711051,
        'tenphuongxa' => 'Xã Vĩnh Thịnh',
      ),
      51 =>
      array(
        'maphuongxa' => 50711052,
        'tenphuongxa' => 'Xã Vĩnh Quang',
      ),
      52 =>
      array(
        'maphuongxa' => 50711053,
        'tenphuongxa' => 'Xã Vĩnh Sơn',
      ),
      53 =>
      array(
        'maphuongxa' => 50703054,
        'tenphuongxa' => 'Xã An Hoà',
      ),
      54 =>
      array(
        'maphuongxa' => 50703055,
        'tenphuongxa' => 'Xã An Lão',
      ),
      55 =>
      array(
        'maphuongxa' => 50703056,
        'tenphuongxa' => 'Xã An Vinh',
      ),
      56 =>
      array(
        'maphuongxa' => 50703057,
        'tenphuongxa' => 'Xã An Toàn',
      ),
      57 =>
      array(
        'maphuongxa' => 60301058,
        'tenphuongxa' => 'Phường Pleiku',
      ),
      58 =>
      array(
        'maphuongxa' => 60301059,
        'tenphuongxa' => 'Phường Hội Phú',
      ),
      59 =>
      array(
        'maphuongxa' => 60301060,
        'tenphuongxa' => 'Phường Thống Nhất',
      ),
      60 =>
      array(
        'maphuongxa' => 60301061,
        'tenphuongxa' => 'Phường Diên Hồng',
      ),
      61 =>
      array(
        'maphuongxa' => 60301062,
        'tenphuongxa' => 'Phường An Phú',
      ),
      62 =>
      array(
        'maphuongxa' => 60301063,
        'tenphuongxa' => 'Xã Biển Hồ',
      ),
      63 =>
      array(
        'maphuongxa' => 60301064,
        'tenphuongxa' => 'Xã Gào',
      ),
      64 =>
      array(
        'maphuongxa' => 60307065,
        'tenphuongxa' => 'Xã Ia Ly',
      ),
      65 =>
      array(
        'maphuongxa' => 60307066,
        'tenphuongxa' => 'Xã Chư Păh',
      ),
      66 =>
      array(
        'maphuongxa' => 60307067,
        'tenphuongxa' => 'Xã Ia Khươl',
      ),
      67 =>
      array(
        'maphuongxa' => 60307068,
        'tenphuongxa' => 'Xã Ia Phí',
      ),
      68 =>
      array(
        'maphuongxa' => 60317069,
        'tenphuongxa' => 'Xã Chư Prông',
      ),
      69 =>
      array(
        'maphuongxa' => 60317070,
        'tenphuongxa' => 'Xã Bàu Cạn',
      ),
      70 =>
      array(
        'maphuongxa' => 60317071,
        'tenphuongxa' => 'Xã Ia Boòng',
      ),
      71 =>
      array(
        'maphuongxa' => 60317072,
        'tenphuongxa' => 'Xã Ia Lâu',
      ),
      72 =>
      array(
        'maphuongxa' => 60317073,
        'tenphuongxa' => 'Xã Ia Pia',
      ),
      73 =>
      array(
        'maphuongxa' => 60317074,
        'tenphuongxa' => 'Xã Ia Tôr',
      ),
      74 =>
      array(
        'maphuongxa' => 60319075,
        'tenphuongxa' => 'Xã Chư Sê',
      ),
      75 =>
      array(
        'maphuongxa' => 60319076,
        'tenphuongxa' => 'Xã Bờ Ngoong',
      ),
      76 =>
      array(
        'maphuongxa' => 60319077,
        'tenphuongxa' => 'Xã Ia Ko',
      ),
      77 =>
      array(
        'maphuongxa' => 60319078,
        'tenphuongxa' => 'Xã Albá',
      ),
      78 =>
      array(
        'maphuongxa' => 60331079,
        'tenphuongxa' => 'Xã Chư Pưh',
      ),
      79 =>
      array(
        'maphuongxa' => 60331080,
        'tenphuongxa' => 'Xã Ia Le',
      ),
      80 =>
      array(
        'maphuongxa' => 60331081,
        'tenphuongxa' => 'Xã Ia Hrú',
      ),
      81 =>
      array(
        'maphuongxa' => 60311082,
        'tenphuongxa' => 'Phường An Khê',
      ),
      82 =>
      array(
        'maphuongxa' => 60311083,
        'tenphuongxa' => 'Phường An Bình',
      ),
      83 =>
      array(
        'maphuongxa' => 60311084,
        'tenphuongxa' => 'Xã Cửu An',
      ),
      84 =>
      array(
        'maphuongxa' => 60327085,
        'tenphuongxa' => 'Xã Đak Pơ',
      ),
      85 =>
      array(
        'maphuongxa' => 60327086,
        'tenphuongxa' => 'Xã Ya Hội',
      ),
      86 =>
      array(
        'maphuongxa' => 60303087,
        'tenphuongxa' => 'Xã Kbang',
      ),
      87 =>
      array(
        'maphuongxa' => 60303088,
        'tenphuongxa' => 'Xã Kông Bơ La',
      ),
      88 =>
      array(
        'maphuongxa' => 60303089,
        'tenphuongxa' => 'Xã Tơ Tung',
      ),
      89 =>
      array(
        'maphuongxa' => 60303090,
        'tenphuongxa' => 'Xã Sơn Lang',
      ),
      90 =>
      array(
        'maphuongxa' => 60303091,
        'tenphuongxa' => 'Xã Đak Rong',
      ),
      91 =>
      array(
        'maphuongxa' => 60313092,
        'tenphuongxa' => 'Xã Kông Chro',
      ),
      92 =>
      array(
        'maphuongxa' => 60313093,
        'tenphuongxa' => 'Xã Ya Ma',
      ),
      93 =>
      array(
        'maphuongxa' => 60313094,
        'tenphuongxa' => 'Xã Chư Krey',
      ),
      94 =>
      array(
        'maphuongxa' => 60313095,
        'tenphuongxa' => 'Xã SRó',
      ),
      95 =>
      array(
        'maphuongxa' => 60313096,
        'tenphuongxa' => 'Xã Đăk Song',
      ),
      96 =>
      array(
        'maphuongxa' => 60313097,
        'tenphuongxa' => 'Xã Chơ Long',
      ),
      97 =>
      array(
        'maphuongxa' => 60321098,
        'tenphuongxa' => 'Phường Ayun Pa',
      ),
      98 =>
      array(
        'maphuongxa' => 60321099,
        'tenphuongxa' => 'Xã Ia Rbol',
      ),
      99 =>
      array(
        'maphuongxa' => 60321100,
        'tenphuongxa' => 'Xã Ia Sao',
      ),
      100 =>
      array(
        'maphuongxa' => 60329101,
        'tenphuongxa' => 'Xã Phú Thiện',
      ),
      101 =>
      array(
        'maphuongxa' => 60329102,
        'tenphuongxa' => 'Xã Chư A Thai',
      ),
      102 =>
      array(
        'maphuongxa' => 60329103,
        'tenphuongxa' => 'Xã Ia Hiao',
      ),
      103 =>
      array(
        'maphuongxa' => 60320104,
        'tenphuongxa' => 'Xã Pờ Tó',
      ),
      104 =>
      array(
        'maphuongxa' => 60320105,
        'tenphuongxa' => 'Xã Ia Pa',
      ),
      105 =>
      array(
        'maphuongxa' => 60320106,
        'tenphuongxa' => 'Xã Ia Tul',
      ),
      106 =>
      array(
        'maphuongxa' => 60323107,
        'tenphuongxa' => 'Xã Phú Túc',
      ),
      107 =>
      array(
        'maphuongxa' => 60323108,
        'tenphuongxa' => 'Xã Ia Dreh',
      ),
      108 =>
      array(
        'maphuongxa' => 60323109,
        'tenphuongxa' => 'Xã Ia Rsai',
      ),
      109 =>
      array(
        'maphuongxa' => 60323110,
        'tenphuongxa' => 'Xã Uar',
      ),
      110 =>
      array(
        'maphuongxa' => 60325111,
        'tenphuongxa' => 'Xã Đak Đoa',
      ),
      111 =>
      array(
        'maphuongxa' => 60325112,
        'tenphuongxa' => 'Xã Kon Gang',
      ),
      112 =>
      array(
        'maphuongxa' => 60325113,
        'tenphuongxa' => 'Xã Ia Băng',
      ),
      113 =>
      array(
        'maphuongxa' => 60325114,
        'tenphuongxa' => 'Xã KDang',
      ),
      114 =>
      array(
        'maphuongxa' => 60325115,
        'tenphuongxa' => 'Xã Đak Sơmei',
      ),
      115 =>
      array(
        'maphuongxa' => 60305116,
        'tenphuongxa' => 'Xã Mang Yang',
      ),
      116 =>
      array(
        'maphuongxa' => 60305117,
        'tenphuongxa' => 'Xã Lơ Pang',
      ),
      117 =>
      array(
        'maphuongxa' => 60305118,
        'tenphuongxa' => 'Xã Kon Chiêng',
      ),
      118 =>
      array(
        'maphuongxa' => 60305119,
        'tenphuongxa' => 'Xã Hra',
      ),
      119 =>
      array(
        'maphuongxa' => 60305120,
        'tenphuongxa' => 'Xã Ayun',
      ),
      120 =>
      array(
        'maphuongxa' => 60309121,
        'tenphuongxa' => 'Xã Ia Grai',
      ),
      121 =>
      array(
        'maphuongxa' => 60309122,
        'tenphuongxa' => 'Xã Ia Krái',
      ),
      122 =>
      array(
        'maphuongxa' => 60309123,
        'tenphuongxa' => 'Xã Ia Hrung',
      ),
      123 =>
      array(
        'maphuongxa' => 60315124,
        'tenphuongxa' => 'Xã Đức Cơ',
      ),
      124 =>
      array(
        'maphuongxa' => 60315125,
        'tenphuongxa' => 'Xã Ia Dơk',
      ),
      125 =>
      array(
        'maphuongxa' => 60315126,
        'tenphuongxa' => 'Xã Ia Krêl',
      ),
      126 =>
      array(
        'maphuongxa' => 50701127,
        'tenphuongxa' => 'Xã Nhơn Châu',
      ),
      127 =>
      array(
        'maphuongxa' => 60317128,
        'tenphuongxa' => 'Xã Ia Púch',
      ),
      128 =>
      array(
        'maphuongxa' => 60317129,
        'tenphuongxa' => 'Xã Ia Mơ',
      ),
      129 =>
      array(
        'maphuongxa' => 60315130,
        'tenphuongxa' => 'Xã Ia Pnôn',
      ),
      130 =>
      array(
        'maphuongxa' => 60315131,
        'tenphuongxa' => 'Xã Ia Nan',
      ),
      131 =>
      array(
        'maphuongxa' => 60315132,
        'tenphuongxa' => 'Xã Ia Dom',
      ),
      132 =>
      array(
        'maphuongxa' => 60309133,
        'tenphuongxa' => 'Xã Ia Chia',
      ),
      133 =>
      array(
        'maphuongxa' => 60309134,
        'tenphuongxa' => 'Xã Ia O',
      ),
      134 =>
      array(
        'maphuongxa' => 60303135,
        'tenphuongxa' => 'Xã Krong',
      ),
    ),
  ),
  24 =>
  array(
    'matinhBNV' => 25,
    'matinhTMS' => '605',
    'tentinhmoi' => 'Tỉnh Đắk Lắk',
    'phuongxa' =>
    array(
      0 =>
      array(
        'maphuongxa' => 60501001,
        'tenphuongxa' => 'Xã Hoà Phú',
      ),
      1 =>
      array(
        'maphuongxa' => 60501002,
        'tenphuongxa' => 'Phường Buôn Ma Thuột',
      ),
      2 =>
      array(
        'maphuongxa' => 60501003,
        'tenphuongxa' => 'Phường Tân An',
      ),
      3 =>
      array(
        'maphuongxa' => 60501004,
        'tenphuongxa' => 'Phường Tân Lập',
      ),
      4 =>
      array(
        'maphuongxa' => 60501005,
        'tenphuongxa' => 'Phường Thành Nhất',
      ),
      5 =>
      array(
        'maphuongxa' => 60501006,
        'tenphuongxa' => 'Phường Ea Kao',
      ),
      6 =>
      array(
        'maphuongxa' => 60509007,
        'tenphuongxa' => 'Xã Ea Drông',
      ),
      7 =>
      array(
        'maphuongxa' => 60509008,
        'tenphuongxa' => 'Phường Buôn Hồ',
      ),
      8 =>
      array(
        'maphuongxa' => 60509009,
        'tenphuongxa' => 'Phường Cư Bao',
      ),
      9 =>
      array(
        'maphuongxa' => 60505010,
        'tenphuongxa' => 'Xã Ea Súp',
      ),
      10 =>
      array(
        'maphuongxa' => 60505011,
        'tenphuongxa' => 'Xã Ea Rốk',
      ),
      11 =>
      array(
        'maphuongxa' => 60505012,
        'tenphuongxa' => 'Xã Ea Bung',
      ),
      12 =>
      array(
        'maphuongxa' => 60505013,
        'tenphuongxa' => 'Xã Ia Rvê',
      ),
      13 =>
      array(
        'maphuongxa' => 60505014,
        'tenphuongxa' => 'Xã Ia Lốp',
      ),
      14 =>
      array(
        'maphuongxa' => 60511015,
        'tenphuongxa' => 'Xã Ea Wer',
      ),
      15 =>
      array(
        'maphuongxa' => 60511016,
        'tenphuongxa' => 'Xã Ea Nuôl',
      ),
      16 =>
      array(
        'maphuongxa' => 60511017,
        'tenphuongxa' => 'Xã Buôn Đôn',
      ),
      17 =>
      array(
        'maphuongxa' => 60513018,
        'tenphuongxa' => 'Xã Ea Kiết',
      ),
      18 =>
      array(
        'maphuongxa' => 60513019,
        'tenphuongxa' => 'Xã Ea M’Droh',
      ),
      19 =>
      array(
        'maphuongxa' => 60513020,
        'tenphuongxa' => 'Xã Quảng Phú',
      ),
      20 =>
      array(
        'maphuongxa' => 60513021,
        'tenphuongxa' => 'Xã Cuôr Đăng',
      ),
      21 =>
      array(
        'maphuongxa' => 60513022,
        'tenphuongxa' => 'Xã Cư M’gar',
      ),
      22 =>
      array(
        'maphuongxa' => 60513023,
        'tenphuongxa' => 'Xã Ea Tul',
      ),
      23 =>
      array(
        'maphuongxa' => 60539024,
        'tenphuongxa' => 'Xã Pơng Drang',
      ),
      24 =>
      array(
        'maphuongxa' => 60539025,
        'tenphuongxa' => 'Xã Krông Búk',
      ),
      25 =>
      array(
        'maphuongxa' => 60539026,
        'tenphuongxa' => 'Xã Cư Pơng',
      ),
      26 =>
      array(
        'maphuongxa' => 60503027,
        'tenphuongxa' => 'Xã Ea Khăl',
      ),
      27 =>
      array(
        'maphuongxa' => 60503028,
        'tenphuongxa' => 'Xã Ea Drăng',
      ),
      28 =>
      array(
        'maphuongxa' => 60503029,
        'tenphuongxa' => 'Xã Ea Wy',
      ),
      29 =>
      array(
        'maphuongxa' => 60503030,
        'tenphuongxa' => 'Xã Ea H’leo',
      ),
      30 =>
      array(
        'maphuongxa' => 60503031,
        'tenphuongxa' => 'Xã Ea Hiao',
      ),
      31 =>
      array(
        'maphuongxa' => 60507032,
        'tenphuongxa' => 'Xã Krông Năng',
      ),
      32 =>
      array(
        'maphuongxa' => 60507033,
        'tenphuongxa' => 'Xã Dliê Ya',
      ),
      33 =>
      array(
        'maphuongxa' => 60507034,
        'tenphuongxa' => 'Xã Tam Giang',
      ),
      34 =>
      array(
        'maphuongxa' => 60507035,
        'tenphuongxa' => 'Xã Phú Xuân',
      ),
      35 =>
      array(
        'maphuongxa' => 60519036,
        'tenphuongxa' => 'Xã Krông Pắc',
      ),
      36 =>
      array(
        'maphuongxa' => 60519037,
        'tenphuongxa' => 'Xã Ea Knuếc',
      ),
      37 =>
      array(
        'maphuongxa' => 60519038,
        'tenphuongxa' => 'Xã Tân Tiến',
      ),
      38 =>
      array(
        'maphuongxa' => 60519039,
        'tenphuongxa' => 'Xã Ea Phê',
      ),
      39 =>
      array(
        'maphuongxa' => 60519040,
        'tenphuongxa' => 'Xã Ea Kly',
      ),
      40 =>
      array(
        'maphuongxa' => 60519041,
        'tenphuongxa' => 'Xã Vụ Bổn',
      ),
      41 =>
      array(
        'maphuongxa' => 60515042,
        'tenphuongxa' => 'Xã Ea Kar',
      ),
      42 =>
      array(
        'maphuongxa' => 60515043,
        'tenphuongxa' => 'Xã Ea Ô',
      ),
      43 =>
      array(
        'maphuongxa' => 60515044,
        'tenphuongxa' => 'Xã Ea Knốp',
      ),
      44 =>
      array(
        'maphuongxa' => 60515045,
        'tenphuongxa' => 'Xã Cư Yang',
      ),
      45 =>
      array(
        'maphuongxa' => 60515046,
        'tenphuongxa' => 'Xã Ea Păl',
      ),
      46 =>
      array(
        'maphuongxa' => 60517047,
        'tenphuongxa' => 'Xã M’Drắk',
      ),
      47 =>
      array(
        'maphuongxa' => 60517048,
        'tenphuongxa' => 'Xã Ea Riêng',
      ),
      48 =>
      array(
        'maphuongxa' => 60517049,
        'tenphuongxa' => 'Xã Cư M’ta',
      ),
      49 =>
      array(
        'maphuongxa' => 60517050,
        'tenphuongxa' => 'Xã Krông Á',
      ),
      50 =>
      array(
        'maphuongxa' => 60517051,
        'tenphuongxa' => 'Xã Cư Prao',
      ),
      51 =>
      array(
        'maphuongxa' => 60517052,
        'tenphuongxa' => 'Xã Ea Trang',
      ),
      52 =>
      array(
        'maphuongxa' => 60525053,
        'tenphuongxa' => 'Xã Hoà Sơn',
      ),
      53 =>
      array(
        'maphuongxa' => 60525054,
        'tenphuongxa' => 'Xã Dang Kang',
      ),
      54 =>
      array(
        'maphuongxa' => 60525055,
        'tenphuongxa' => 'Xã Krông Bông',
      ),
      55 =>
      array(
        'maphuongxa' => 60525056,
        'tenphuongxa' => 'Xã Yang Mao',
      ),
      56 =>
      array(
        'maphuongxa' => 60525057,
        'tenphuongxa' => 'Xã Cư Pui',
      ),
      57 =>
      array(
        'maphuongxa' => 60531058,
        'tenphuongxa' => 'Xã Liên Sơn Lắk',
      ),
      58 =>
      array(
        'maphuongxa' => 60531059,
        'tenphuongxa' => 'Xã Đắk Liêng',
      ),
      59 =>
      array(
        'maphuongxa' => 60531060,
        'tenphuongxa' => 'Xã Nam Ka',
      ),
      60 =>
      array(
        'maphuongxa' => 60531061,
        'tenphuongxa' => 'Xã Đắk Phơi',
      ),
      61 =>
      array(
        'maphuongxa' => 60531062,
        'tenphuongxa' => 'Xã Krông Nô',
      ),
      62 =>
      array(
        'maphuongxa' => 60537063,
        'tenphuongxa' => 'Xã Ea Ning',
      ),
      63 =>
      array(
        'maphuongxa' => 60537064,
        'tenphuongxa' => 'Xã Dray Bhăng',
      ),
      64 =>
      array(
        'maphuongxa' => 60537065,
        'tenphuongxa' => 'Xã Ea Ktur',
      ),
      65 =>
      array(
        'maphuongxa' => 60523066,
        'tenphuongxa' => 'Xã Krông Ana',
      ),
      66 =>
      array(
        'maphuongxa' => 60523067,
        'tenphuongxa' => 'Xã Dur Kmăl',
      ),
      67 =>
      array(
        'maphuongxa' => 60523068,
        'tenphuongxa' => 'Xã Ea Na',
      ),
      68 =>
      array(
        'maphuongxa' => 50901069,
        'tenphuongxa' => 'Phường Tuy Hòa',
      ),
      69 =>
      array(
        'maphuongxa' => 50901070,
        'tenphuongxa' => 'Phường Phú Yên',
      ),
      70 =>
      array(
        'maphuongxa' => 50901071,
        'tenphuongxa' => 'Phường Bình Kiến',
      ),
      71 =>
      array(
        'maphuongxa' => 50905072,
        'tenphuongxa' => 'Xã Xuân Thọ',
      ),
      72 =>
      array(
        'maphuongxa' => 50905073,
        'tenphuongxa' => 'Xã Xuân Cảnh',
      ),
      73 =>
      array(
        'maphuongxa' => 50905074,
        'tenphuongxa' => 'Xã Xuân Lộc',
      ),
      74 =>
      array(
        'maphuongxa' => 50905075,
        'tenphuongxa' => 'Phường Xuân Đài',
      ),
      75 =>
      array(
        'maphuongxa' => 50905076,
        'tenphuongxa' => 'Phường Sông Cầu',
      ),
      76 =>
      array(
        'maphuongxa' => 50911077,
        'tenphuongxa' => 'Xã Hòa Xuân',
      ),
      77 =>
      array(
        'maphuongxa' => 50911078,
        'tenphuongxa' => 'Phường Đông Hòa',
      ),
      78 =>
      array(
        'maphuongxa' => 50911079,
        'tenphuongxa' => 'Phường Hòa Hiệp',
      ),
      79 =>
      array(
        'maphuongxa' => 50907080,
        'tenphuongxa' => 'Xã Tuy An Bắc',
      ),
      80 =>
      array(
        'maphuongxa' => 50907081,
        'tenphuongxa' => 'Xã Tuy An Đông',
      ),
      81 =>
      array(
        'maphuongxa' => 50907082,
        'tenphuongxa' => 'Xã Ô Loan',
      ),
      82 =>
      array(
        'maphuongxa' => 50907083,
        'tenphuongxa' => 'Xã Tuy An Nam',
      ),
      83 =>
      array(
        'maphuongxa' => 50907084,
        'tenphuongxa' => 'Xã Tuy An Tây',
      ),
      84 =>
      array(
        'maphuongxa' => 50915085,
        'tenphuongxa' => 'Xã Phú Hòa 1',
      ),
      85 =>
      array(
        'maphuongxa' => 50915086,
        'tenphuongxa' => 'Xã Phú Hòa 2',
      ),
      86 =>
      array(
        'maphuongxa' => 50912087,
        'tenphuongxa' => 'Xã Tây Hòa',
      ),
      87 =>
      array(
        'maphuongxa' => 50912088,
        'tenphuongxa' => 'Xã Hòa Thịnh',
      ),
      88 =>
      array(
        'maphuongxa' => 50912089,
        'tenphuongxa' => 'Xã Hòa Mỹ',
      ),
      89 =>
      array(
        'maphuongxa' => 50912090,
        'tenphuongxa' => 'Xã Sơn Thành',
      ),
      90 =>
      array(
        'maphuongxa' => 50909091,
        'tenphuongxa' => 'Xã Sơn Hòa',
      ),
      91 =>
      array(
        'maphuongxa' => 50909092,
        'tenphuongxa' => 'Xã Vân Hòa',
      ),
      92 =>
      array(
        'maphuongxa' => 50909093,
        'tenphuongxa' => 'Xã Tây Sơn',
      ),
      93 =>
      array(
        'maphuongxa' => 50909094,
        'tenphuongxa' => 'Xã Suối Trai',
      ),
      94 =>
      array(
        'maphuongxa' => 50913095,
        'tenphuongxa' => 'Xã Ea Ly',
      ),
      95 =>
      array(
        'maphuongxa' => 50913096,
        'tenphuongxa' => 'Xã Ea Bá',
      ),
      96 =>
      array(
        'maphuongxa' => 50913097,
        'tenphuongxa' => 'Xã Đức Bình',
      ),
      97 =>
      array(
        'maphuongxa' => 50913098,
        'tenphuongxa' => 'Xã Sông Hinh',
      ),
      98 =>
      array(
        'maphuongxa' => 50903099,
        'tenphuongxa' => 'Xã Xuân Lãnh',
      ),
      99 =>
      array(
        'maphuongxa' => 50903100,
        'tenphuongxa' => 'Xã Phú Mỡ',
      ),
      100 =>
      array(
        'maphuongxa' => 50903101,
        'tenphuongxa' => 'Xã Xuân Phước',
      ),
      101 =>
      array(
        'maphuongxa' => 50903102,
        'tenphuongxa' => 'Xã Đồng Xuân',
      ),
    ),
  ),
  25 =>
  array(
    'matinhBNV' => 26,
    'matinhTMS' => '703',
    'tentinhmoi' => 'Tỉnh Lâm Đồng',
    'phuongxa' =>
    array(
      0 =>
      array(
        'maphuongxa' => 70301001,
        'tenphuongxa' => 'Phường Xuân Hương - Đà Lạt',
      ),
      1 =>
      array(
        'maphuongxa' => 70301002,
        'tenphuongxa' => 'Phường Cam Ly - Đà Lạt',
      ),
      2 =>
      array(
        'maphuongxa' => 70301003,
        'tenphuongxa' => 'Phường Lâm Viên - Đà Lạt',
      ),
      3 =>
      array(
        'maphuongxa' => 70301004,
        'tenphuongxa' => 'Phường Xuân Trường - Đà Lạt',
      ),
      4 =>
      array(
        'maphuongxa' => 70305005,
        'tenphuongxa' => 'Phường Langbiang - Đà Lạt',
      ),
      5 =>
      array(
        'maphuongxa' => 70303006,
        'tenphuongxa' => 'Phường 1 Bảo Lộc',
      ),
      6 =>
      array(
        'maphuongxa' => 70303007,
        'tenphuongxa' => 'Phường 2 Bảo Lộc',
      ),
      7 =>
      array(
        'maphuongxa' => 70303008,
        'tenphuongxa' => 'Phường 3 Bảo Lộc',
      ),
      8 =>
      array(
        'maphuongxa' => 70303009,
        'tenphuongxa' => 'Phường B\' Lao',
      ),
      9 =>
      array(
        'maphuongxa' => 70305010,
        'tenphuongxa' => 'Xã Lạc Dương',
      ),
      10 =>
      array(
        'maphuongxa' => 70307011,
        'tenphuongxa' => 'Xã Đơn Dương',
      ),
      11 =>
      array(
        'maphuongxa' => 70307012,
        'tenphuongxa' => 'Xã Ka Đô',
      ),
      12 =>
      array(
        'maphuongxa' => 70307013,
        'tenphuongxa' => 'Xã Quảng Lập',
      ),
      13 =>
      array(
        'maphuongxa' => 70307014,
        'tenphuongxa' => 'Xã D\'Ran',
      ),
      14 =>
      array(
        'maphuongxa' => 70309015,
        'tenphuongxa' => 'Xã Hiệp Thạnh',
      ),
      15 =>
      array(
        'maphuongxa' => 70309016,
        'tenphuongxa' => 'Xã Đức Trọng',
      ),
      16 =>
      array(
        'maphuongxa' => 70309017,
        'tenphuongxa' => 'Xã Tân Hội',
      ),
      17 =>
      array(
        'maphuongxa' => 70309018,
        'tenphuongxa' => 'Xã Tà Hine',
      ),
      18 =>
      array(
        'maphuongxa' => 70309019,
        'tenphuongxa' => 'Xã Tà Năng',
      ),
      19 =>
      array(
        'maphuongxa' => 70311020,
        'tenphuongxa' => 'Xã Đinh Văn - Lâm Hà',
      ),
      20 =>
      array(
        'maphuongxa' => 70311021,
        'tenphuongxa' => 'Xã Phú Sơn - Lâm Hà',
      ),
      21 =>
      array(
        'maphuongxa' => 70311022,
        'tenphuongxa' => 'Xã Nam Hà - Lâm Hà',
      ),
      22 =>
      array(
        'maphuongxa' => 70311023,
        'tenphuongxa' => 'Xã Nam Ban - Lâm Hà',
      ),
      23 =>
      array(
        'maphuongxa' => 70311024,
        'tenphuongxa' => 'Xã Tân Hà - Lâm Hà',
      ),
      24 =>
      array(
        'maphuongxa' => 70311025,
        'tenphuongxa' => 'Xã Phúc Thọ - Lâm Hà',
      ),
      25 =>
      array(
        'maphuongxa' => 70323026,
        'tenphuongxa' => 'Xã Đam Rông 1',
      ),
      26 =>
      array(
        'maphuongxa' => 70323027,
        'tenphuongxa' => 'Xã Đam Rông 2',
      ),
      27 =>
      array(
        'maphuongxa' => 70323028,
        'tenphuongxa' => 'Xã Đam Rông 3',
      ),
      28 =>
      array(
        'maphuongxa' => 70323029,
        'tenphuongxa' => 'Xã Đam Rông 4',
      ),
      29 =>
      array(
        'maphuongxa' => 70315030,
        'tenphuongxa' => 'Xã Di Linh',
      ),
      30 =>
      array(
        'maphuongxa' => 70315031,
        'tenphuongxa' => 'Xã Hoà Ninh',
      ),
      31 =>
      array(
        'maphuongxa' => 70315032,
        'tenphuongxa' => 'Xã Hoà Bắc',
      ),
      32 =>
      array(
        'maphuongxa' => 70315033,
        'tenphuongxa' => 'Xã Đinh Trang Thượng',
      ),
      33 =>
      array(
        'maphuongxa' => 70315034,
        'tenphuongxa' => 'Xã Bảo Thuận',
      ),
      34 =>
      array(
        'maphuongxa' => 70315035,
        'tenphuongxa' => 'Xã Sơn Điền',
      ),
      35 =>
      array(
        'maphuongxa' => 70315036,
        'tenphuongxa' => 'Xã Gia Hiệp',
      ),
      36 =>
      array(
        'maphuongxa' => 70313037,
        'tenphuongxa' => 'Xã Bảo Lâm 1',
      ),
      37 =>
      array(
        'maphuongxa' => 70313038,
        'tenphuongxa' => 'Xã Bảo Lâm 2',
      ),
      38 =>
      array(
        'maphuongxa' => 70313039,
        'tenphuongxa' => 'Xã Bảo Lâm 3',
      ),
      39 =>
      array(
        'maphuongxa' => 70313040,
        'tenphuongxa' => 'Xã Bảo Lâm 4',
      ),
      40 =>
      array(
        'maphuongxa' => 70313041,
        'tenphuongxa' => 'Xã Bảo Lâm 5',
      ),
      41 =>
      array(
        'maphuongxa' => 70317042,
        'tenphuongxa' => 'Xã Đạ Huoai',
      ),
      42 =>
      array(
        'maphuongxa' => 70317043,
        'tenphuongxa' => 'Xã Đạ Huoai 2',
      ),
      43 =>
      array(
        'maphuongxa' => 70317044,
        'tenphuongxa' => 'Xã Đạ Huoai 3',
      ),
      44 =>
      array(
        'maphuongxa' => 70317045,
        'tenphuongxa' => 'Xã Đạ Tẻh',
      ),
      45 =>
      array(
        'maphuongxa' => 70317046,
        'tenphuongxa' => 'Xã Đạ Tẻh 2',
      ),
      46 =>
      array(
        'maphuongxa' => 70317047,
        'tenphuongxa' => 'Xã Đạ Tẻh 3',
      ),
      47 =>
      array(
        'maphuongxa' => 70317048,
        'tenphuongxa' => 'Xã Cát Tiên',
      ),
      48 =>
      array(
        'maphuongxa' => 70317049,
        'tenphuongxa' => 'Xã Cát Tiên 2',
      ),
      49 =>
      array(
        'maphuongxa' => 70317050,
        'tenphuongxa' => 'Xã Cát Tiên 3',
      ),
      50 =>
      array(
        'maphuongxa' => 71501051,
        'tenphuongxa' => 'Phường Hàm Thắng',
      ),
      51 =>
      array(
        'maphuongxa' => 71501052,
        'tenphuongxa' => 'Phường Bình Thuận',
      ),
      52 =>
      array(
        'maphuongxa' => 71501053,
        'tenphuongxa' => 'Phường Mũi Né',
      ),
      53 =>
      array(
        'maphuongxa' => 71501054,
        'tenphuongxa' => 'Phường Phú Thuỷ',
      ),
      54 =>
      array(
        'maphuongxa' => 71501055,
        'tenphuongxa' => 'Phường Phan Thiết',
      ),
      55 =>
      array(
        'maphuongxa' => 71501056,
        'tenphuongxa' => 'Phường Tiến Thành',
      ),
      56 =>
      array(
        'maphuongxa' => 71513057,
        'tenphuongxa' => 'Phường La Gi',
      ),
      57 =>
      array(
        'maphuongxa' => 71513058,
        'tenphuongxa' => 'Phường Phước Hội',
      ),
      58 =>
      array(
        'maphuongxa' => 71501059,
        'tenphuongxa' => 'Xã Tuyên Quang',
      ),
      59 =>
      array(
        'maphuongxa' => 71513060,
        'tenphuongxa' => 'Xã Tân Hải',
      ),
      60 =>
      array(
        'maphuongxa' => 71503061,
        'tenphuongxa' => 'Xã Vĩnh Hảo',
      ),
      61 =>
      array(
        'maphuongxa' => 71503062,
        'tenphuongxa' => 'Xã Liên Hương',
      ),
      62 =>
      array(
        'maphuongxa' => 71503063,
        'tenphuongxa' => 'Xã Tuy Phong',
      ),
      63 =>
      array(
        'maphuongxa' => 71503064,
        'tenphuongxa' => 'Xã Phan Rí Cửa',
      ),
      64 =>
      array(
        'maphuongxa' => 71505065,
        'tenphuongxa' => 'Xã Bắc Bình',
      ),
      65 =>
      array(
        'maphuongxa' => 71505066,
        'tenphuongxa' => 'Xã Hồng Thái',
      ),
      66 =>
      array(
        'maphuongxa' => 71505067,
        'tenphuongxa' => 'Xã Hải Ninh',
      ),
      67 =>
      array(
        'maphuongxa' => 71505068,
        'tenphuongxa' => 'Xã Phan Sơn',
      ),
      68 =>
      array(
        'maphuongxa' => 71505069,
        'tenphuongxa' => 'Xã Sông Lũy',
      ),
      69 =>
      array(
        'maphuongxa' => 71505070,
        'tenphuongxa' => 'Xã Lương Sơn',
      ),
      70 =>
      array(
        'maphuongxa' => 71505071,
        'tenphuongxa' => 'Xã Hoà Thắng',
      ),
      71 =>
      array(
        'maphuongxa' => 71507072,
        'tenphuongxa' => 'Xã Đông Giang',
      ),
      72 =>
      array(
        'maphuongxa' => 71507073,
        'tenphuongxa' => 'Xã La Dạ',
      ),
      73 =>
      array(
        'maphuongxa' => 71507074,
        'tenphuongxa' => 'Xã Hàm Thuận Bắc',
      ),
      74 =>
      array(
        'maphuongxa' => 71507075,
        'tenphuongxa' => 'Xã Hàm Thuận',
      ),
      75 =>
      array(
        'maphuongxa' => 71507076,
        'tenphuongxa' => 'Xã Hồng Sơn',
      ),
      76 =>
      array(
        'maphuongxa' => 71507077,
        'tenphuongxa' => 'Xã Hàm Liêm',
      ),
      77 =>
      array(
        'maphuongxa' => 71509078,
        'tenphuongxa' => 'Xã Hàm Thạnh',
      ),
      78 =>
      array(
        'maphuongxa' => 71509079,
        'tenphuongxa' => 'Xã Hàm Kiệm',
      ),
      79 =>
      array(
        'maphuongxa' => 71509080,
        'tenphuongxa' => 'Xã Tân Thành',
      ),
      80 =>
      array(
        'maphuongxa' => 71509081,
        'tenphuongxa' => 'Xã Hàm Thuận Nam',
      ),
      81 =>
      array(
        'maphuongxa' => 71509082,
        'tenphuongxa' => 'Xã Tân Lập',
      ),
      82 =>
      array(
        'maphuongxa' => 71514083,
        'tenphuongxa' => 'Xã Tân Minh',
      ),
      83 =>
      array(
        'maphuongxa' => 71514084,
        'tenphuongxa' => 'Xã Hàm Tân',
      ),
      84 =>
      array(
        'maphuongxa' => 71514085,
        'tenphuongxa' => 'Xã Sơn Mỹ',
      ),
      85 =>
      array(
        'maphuongxa' => 71511086,
        'tenphuongxa' => 'Xã Bắc Ruộng',
      ),
      86 =>
      array(
        'maphuongxa' => 71511087,
        'tenphuongxa' => 'Xã Nghị Đức',
      ),
      87 =>
      array(
        'maphuongxa' => 71511088,
        'tenphuongxa' => 'Xã Đồng Kho',
      ),
      88 =>
      array(
        'maphuongxa' => 71511089,
        'tenphuongxa' => 'Xã Tánh Linh',
      ),
      89 =>
      array(
        'maphuongxa' => 71511090,
        'tenphuongxa' => 'Xã Suối Kiết',
      ),
      90 =>
      array(
        'maphuongxa' => 71515091,
        'tenphuongxa' => 'Xã Nam Thành',
      ),
      91 =>
      array(
        'maphuongxa' => 71515092,
        'tenphuongxa' => 'Xã Đức Linh',
      ),
      92 =>
      array(
        'maphuongxa' => 71515093,
        'tenphuongxa' => 'Xã Hoài Đức',
      ),
      93 =>
      array(
        'maphuongxa' => 71515094,
        'tenphuongxa' => 'Xã Trà Tân',
      ),
      94 =>
      array(
        'maphuongxa' => 71517095,
        'tenphuongxa' => 'Đặc khu Phú Quý',
      ),
      95 =>
      array(
        'maphuongxa' => 60613096,
        'tenphuongxa' => 'Phường Bắc Gia Nghĩa',
      ),
      96 =>
      array(
        'maphuongxa' => 60613097,
        'tenphuongxa' => 'Phường Nam Gia Nghĩa',
      ),
      97 =>
      array(
        'maphuongxa' => 60613098,
        'tenphuongxa' => 'Phường Đông Gia Nghĩa',
      ),
      98 =>
      array(
        'maphuongxa' => 60603099,
        'tenphuongxa' => 'Xã Đắk Wil',
      ),
      99 =>
      array(
        'maphuongxa' => 60603100,
        'tenphuongxa' => 'Xã Nam Dong',
      ),
      100 =>
      array(
        'maphuongxa' => 60603101,
        'tenphuongxa' => 'Xã Cư Jút',
      ),
      101 =>
      array(
        'maphuongxa' => 60607102,
        'tenphuongxa' => 'Xã Thuận An',
      ),
      102 =>
      array(
        'maphuongxa' => 60607103,
        'tenphuongxa' => 'Xã Đức Lập',
      ),
      103 =>
      array(
        'maphuongxa' => 60607104,
        'tenphuongxa' => 'Xã Đắk Mil',
      ),
      104 =>
      array(
        'maphuongxa' => 60607105,
        'tenphuongxa' => 'Xã Đắk Sắk',
      ),
      105 =>
      array(
        'maphuongxa' => 60605106,
        'tenphuongxa' => 'Xã Nam Đà',
      ),
      106 =>
      array(
        'maphuongxa' => 60605107,
        'tenphuongxa' => 'Xã Krông Nô',
      ),
      107 =>
      array(
        'maphuongxa' => 60605108,
        'tenphuongxa' => 'Xã Nâm Nung',
      ),
      108 =>
      array(
        'maphuongxa' => 60605109,
        'tenphuongxa' => 'Xã Quảng Phú',
      ),
      109 =>
      array(
        'maphuongxa' => 60609110,
        'tenphuongxa' => 'Xã Đắk song',
      ),
      110 =>
      array(
        'maphuongxa' => 60609111,
        'tenphuongxa' => 'Xã Đức An',
      ),
      111 =>
      array(
        'maphuongxa' => 60609112,
        'tenphuongxa' => 'Xã Thuận Hạnh',
      ),
      112 =>
      array(
        'maphuongxa' => 60609113,
        'tenphuongxa' => 'Xã Trường Xuân',
      ),
      113 =>
      array(
        'maphuongxa' => 60615114,
        'tenphuongxa' => 'Xã Tà Đùng',
      ),
      114 =>
      array(
        'maphuongxa' => 60615115,
        'tenphuongxa' => 'Xã Quảng Khê',
      ),
      115 =>
      array(
        'maphuongxa' => 60617116,
        'tenphuongxa' => 'Xã Quảng Tân',
      ),
      116 =>
      array(
        'maphuongxa' => 60617117,
        'tenphuongxa' => 'Xã Tuy Đức',
      ),
      117 =>
      array(
        'maphuongxa' => 60611118,
        'tenphuongxa' => 'Xã Kiến Đức',
      ),
      118 =>
      array(
        'maphuongxa' => 60611119,
        'tenphuongxa' => 'Xã Nhân Cơ',
      ),
      119 =>
      array(
        'maphuongxa' => 60611120,
        'tenphuongxa' => 'Xã Quảng Tín',
      ),
      120 =>
      array(
        'maphuongxa' => 70309121,
        'tenphuongxa' => 'Xã Ninh Gia',
      ),
      121 =>
      array(
        'maphuongxa' => 60615122,
        'tenphuongxa' => 'Xã Quảng Hoà',
      ),
      122 =>
      array(
        'maphuongxa' => 60615123,
        'tenphuongxa' => 'Xã Quảng Sơn',
      ),
      123 =>
      array(
        'maphuongxa' => 60617124,
        'tenphuongxa' => 'Xã Quảng Trực',
      ),
    ),
  ),
  26 =>
  array(
    'matinhBNV' => 27,
    'matinhTMS' => '709',
    'tentinhmoi' => 'Tỉnh Tây Ninh',
    'phuongxa' =>
    array(
      0 =>
      array(
        'maphuongxa' => 80103001,
        'tenphuongxa' => 'Xã Hưng Điền',
      ),
      1 =>
      array(
        'maphuongxa' => 80103002,
        'tenphuongxa' => 'Xã Vĩnh Thạnh',
      ),
      2 =>
      array(
        'maphuongxa' => 80103003,
        'tenphuongxa' => 'Xã Tân Hưng',
      ),
      3 =>
      array(
        'maphuongxa' => 80103004,
        'tenphuongxa' => 'Xã Vĩnh Châu',
      ),
      4 =>
      array(
        'maphuongxa' => 80105005,
        'tenphuongxa' => 'Xã Tuyên Bình',
      ),
      5 =>
      array(
        'maphuongxa' => 80105006,
        'tenphuongxa' => 'Xã Vĩnh Hưng',
      ),
      6 =>
      array(
        'maphuongxa' => 80105007,
        'tenphuongxa' => 'Xã Khánh Hưng',
      ),
      7 =>
      array(
        'maphuongxa' => 80129008,
        'tenphuongxa' => 'Xã Tuyên Thạnh',
      ),
      8 =>
      array(
        'maphuongxa' => 80129009,
        'tenphuongxa' => 'Xã Bình Hiệp',
      ),
      9 =>
      array(
        'maphuongxa' => 80129010,
        'tenphuongxa' => 'Phường Kiến Tường',
      ),
      10 =>
      array(
        'maphuongxa' => 80107011,
        'tenphuongxa' => 'Xã Bình Hoà',
      ),
      11 =>
      array(
        'maphuongxa' => 80107012,
        'tenphuongxa' => 'Xã Mộc Hoá',
      ),
      12 =>
      array(
        'maphuongxa' => 80109013,
        'tenphuongxa' => 'Xã Hậu Thạnh',
      ),
      13 =>
      array(
        'maphuongxa' => 80109014,
        'tenphuongxa' => 'Xã Nhơn Hoà Lập',
      ),
      14 =>
      array(
        'maphuongxa' => 80109015,
        'tenphuongxa' => 'Xã Nhơn Ninh',
      ),
      15 =>
      array(
        'maphuongxa' => 80109016,
        'tenphuongxa' => 'Xã Tân Thạnh',
      ),
      16 =>
      array(
        'maphuongxa' => 80111017,
        'tenphuongxa' => 'Xã Bình Thành',
      ),
      17 =>
      array(
        'maphuongxa' => 80111018,
        'tenphuongxa' => 'Xã Thạnh Phước',
      ),
      18 =>
      array(
        'maphuongxa' => 80111019,
        'tenphuongxa' => 'Xã Thạnh Hóa',
      ),
      19 =>
      array(
        'maphuongxa' => 80111020,
        'tenphuongxa' => 'Xã Tân Tây',
      ),
      20 =>
      array(
        'maphuongxa' => 80119021,
        'tenphuongxa' => 'Xã Thủ Thừa',
      ),
      21 =>
      array(
        'maphuongxa' => 80119022,
        'tenphuongxa' => 'Xã Mỹ An',
      ),
      22 =>
      array(
        'maphuongxa' => 80119023,
        'tenphuongxa' => 'Xã Mỹ Thạnh',
      ),
      23 =>
      array(
        'maphuongxa' => 80119024,
        'tenphuongxa' => 'Xã Tân Long',
      ),
      24 =>
      array(
        'maphuongxa' => 80113025,
        'tenphuongxa' => 'Xã Mỹ Quý',
      ),
      25 =>
      array(
        'maphuongxa' => 80113026,
        'tenphuongxa' => 'Xã Đông Thành',
      ),
      26 =>
      array(
        'maphuongxa' => 80113027,
        'tenphuongxa' => 'Xã Đức Huệ',
      ),
      27 =>
      array(
        'maphuongxa' => 80115028,
        'tenphuongxa' => 'Xã An Ninh',
      ),
      28 =>
      array(
        'maphuongxa' => 80115029,
        'tenphuongxa' => 'Xã Hiệp Hoà',
      ),
      29 =>
      array(
        'maphuongxa' => 80115030,
        'tenphuongxa' => 'Xã Hậu Nghĩa',
      ),
      30 =>
      array(
        'maphuongxa' => 80115031,
        'tenphuongxa' => 'Xã Hoà Khánh',
      ),
      31 =>
      array(
        'maphuongxa' => 80115032,
        'tenphuongxa' => 'Xã Đức Lập',
      ),
      32 =>
      array(
        'maphuongxa' => 80115033,
        'tenphuongxa' => 'Xã Mỹ Hạnh',
      ),
      33 =>
      array(
        'maphuongxa' => 80115034,
        'tenphuongxa' => 'Xã Đức Hoà',
      ),
      34 =>
      array(
        'maphuongxa' => 80117035,
        'tenphuongxa' => 'Xã Thạnh Lợi',
      ),
      35 =>
      array(
        'maphuongxa' => 80117036,
        'tenphuongxa' => 'Xã Bình Đức',
      ),
      36 =>
      array(
        'maphuongxa' => 80117037,
        'tenphuongxa' => 'Xã Lương Hoà',
      ),
      37 =>
      array(
        'maphuongxa' => 80117038,
        'tenphuongxa' => 'Xã Bến Lức',
      ),
      38 =>
      array(
        'maphuongxa' => 80117039,
        'tenphuongxa' => 'Xã Mỹ Yên',
      ),
      39 =>
      array(
        'maphuongxa' => 80125040,
        'tenphuongxa' => 'Xã Long Cang',
      ),
      40 =>
      array(
        'maphuongxa' => 80125041,
        'tenphuongxa' => 'Xã Rạch Kiến',
      ),
      41 =>
      array(
        'maphuongxa' => 80125042,
        'tenphuongxa' => 'Xã Mỹ Lệ',
      ),
      42 =>
      array(
        'maphuongxa' => 80125043,
        'tenphuongxa' => 'Xã Tân Lân',
      ),
      43 =>
      array(
        'maphuongxa' => 80125044,
        'tenphuongxa' => 'Xã Cần Đước',
      ),
      44 =>
      array(
        'maphuongxa' => 80125045,
        'tenphuongxa' => 'Xã Long Hựu',
      ),
      45 =>
      array(
        'maphuongxa' => 80127046,
        'tenphuongxa' => 'Xã Phước Lý',
      ),
      46 =>
      array(
        'maphuongxa' => 80127047,
        'tenphuongxa' => 'Xã Mỹ Lộc',
      ),
      47 =>
      array(
        'maphuongxa' => 80127048,
        'tenphuongxa' => 'Xã Cần Giuộc',
      ),
      48 =>
      array(
        'maphuongxa' => 80127049,
        'tenphuongxa' => 'Xã Phước Vĩnh Tây',
      ),
      49 =>
      array(
        'maphuongxa' => 80127050,
        'tenphuongxa' => 'Xã Tân Tập',
      ),
      50 =>
      array(
        'maphuongxa' => 80123051,
        'tenphuongxa' => 'Xã Vàm Cỏ',
      ),
      51 =>
      array(
        'maphuongxa' => 80123052,
        'tenphuongxa' => 'Xã Tân Trụ',
      ),
      52 =>
      array(
        'maphuongxa' => 80123053,
        'tenphuongxa' => 'Xã Nhựt Tảo',
      ),
      53 =>
      array(
        'maphuongxa' => 80121054,
        'tenphuongxa' => 'Xã Thuận Mỹ',
      ),
      54 =>
      array(
        'maphuongxa' => 80121055,
        'tenphuongxa' => 'Xã An Lục Long',
      ),
      55 =>
      array(
        'maphuongxa' => 80121056,
        'tenphuongxa' => 'Xã Tầm Vu',
      ),
      56 =>
      array(
        'maphuongxa' => 80121057,
        'tenphuongxa' => 'Xã Vĩnh Công',
      ),
      57 =>
      array(
        'maphuongxa' => 80101058,
        'tenphuongxa' => 'Phường Long An',
      ),
      58 =>
      array(
        'maphuongxa' => 80101059,
        'tenphuongxa' => 'Phường Tân An',
      ),
      59 =>
      array(
        'maphuongxa' => 80101060,
        'tenphuongxa' => 'Phường Khánh Hậu',
      ),
      60 =>
      array(
        'maphuongxa' => 70901061,
        'tenphuongxa' => 'Phường Tân Ninh',
      ),
      61 =>
      array(
        'maphuongxa' => 70901062,
        'tenphuongxa' => 'Phường Bình Minh',
      ),
      62 =>
      array(
        'maphuongxa' => 70907063,
        'tenphuongxa' => 'Phường Ninh Thạnh',
      ),
      63 =>
      array(
        'maphuongxa' => 70911064,
        'tenphuongxa' => 'Phường Long Hoa',
      ),
      64 =>
      array(
        'maphuongxa' => 70911065,
        'tenphuongxa' => 'Phường Hoà Thành',
      ),
      65 =>
      array(
        'maphuongxa' => 70911066,
        'tenphuongxa' => 'Phường Thanh Điền',
      ),
      66 =>
      array(
        'maphuongxa' => 70917067,
        'tenphuongxa' => 'Phường Trảng Bàng',
      ),
      67 =>
      array(
        'maphuongxa' => 70917068,
        'tenphuongxa' => 'Phường An Tịnh',
      ),
      68 =>
      array(
        'maphuongxa' => 70915069,
        'tenphuongxa' => 'Phường Gò Dầu',
      ),
      69 =>
      array(
        'maphuongxa' => 70915070,
        'tenphuongxa' => 'Phường Gia Lộc',
      ),
      70 =>
      array(
        'maphuongxa' => 70917071,
        'tenphuongxa' => 'Xã Hưng Thuận',
      ),
      71 =>
      array(
        'maphuongxa' => 70917072,
        'tenphuongxa' => 'Xã Phước Chỉ',
      ),
      72 =>
      array(
        'maphuongxa' => 70915073,
        'tenphuongxa' => 'Xã Thạnh Đức',
      ),
      73 =>
      array(
        'maphuongxa' => 70915074,
        'tenphuongxa' => 'Xã Phước Thạnh',
      ),
      74 =>
      array(
        'maphuongxa' => 70915075,
        'tenphuongxa' => 'Xã Truông Mít',
      ),
      75 =>
      array(
        'maphuongxa' => 70907076,
        'tenphuongxa' => 'Xã Lộc Ninh',
      ),
      76 =>
      array(
        'maphuongxa' => 70907077,
        'tenphuongxa' => 'Xã Cầu Khởi',
      ),
      77 =>
      array(
        'maphuongxa' => 70907078,
        'tenphuongxa' => 'Xã Dương Minh Châu',
      ),
      78 =>
      array(
        'maphuongxa' => 70905079,
        'tenphuongxa' => 'Xã Tân Đông',
      ),
      79 =>
      array(
        'maphuongxa' => 70905080,
        'tenphuongxa' => 'Xã Tân Châu',
      ),
      80 =>
      array(
        'maphuongxa' => 70905081,
        'tenphuongxa' => 'Xã Tân Phú',
      ),
      81 =>
      array(
        'maphuongxa' => 70905082,
        'tenphuongxa' => 'Xã Tân Hội',
      ),
      82 =>
      array(
        'maphuongxa' => 70905083,
        'tenphuongxa' => 'Xã Tân Thành',
      ),
      83 =>
      array(
        'maphuongxa' => 70905084,
        'tenphuongxa' => 'Xã Tân Hoà',
      ),
      84 =>
      array(
        'maphuongxa' => 70903085,
        'tenphuongxa' => 'Xã Tân Lập',
      ),
      85 =>
      array(
        'maphuongxa' => 70903086,
        'tenphuongxa' => 'Xã Tân Biên',
      ),
      86 =>
      array(
        'maphuongxa' => 70903087,
        'tenphuongxa' => 'Xã Thạnh Bình',
      ),
      87 =>
      array(
        'maphuongxa' => 70903088,
        'tenphuongxa' => 'Xã Trà Vong',
      ),
      88 =>
      array(
        'maphuongxa' => 70909089,
        'tenphuongxa' => 'Xã Phước Vinh',
      ),
      89 =>
      array(
        'maphuongxa' => 70909090,
        'tenphuongxa' => 'Xã Hoà Hội',
      ),
      90 =>
      array(
        'maphuongxa' => 70909091,
        'tenphuongxa' => 'Xã Ninh Điền',
      ),
      91 =>
      array(
        'maphuongxa' => 70909092,
        'tenphuongxa' => 'Xã Châu Thành',
      ),
      92 =>
      array(
        'maphuongxa' => 70909093,
        'tenphuongxa' => 'Xã Hảo Đước',
      ),
      93 =>
      array(
        'maphuongxa' => 70913094,
        'tenphuongxa' => 'Xã Long Chữ',
      ),
      94 =>
      array(
        'maphuongxa' => 70913095,
        'tenphuongxa' => 'Xã Long Thuận',
      ),
      95 =>
      array(
        'maphuongxa' => 70913096,
        'tenphuongxa' => 'Xã Bến Cầu',
      ),
    ),
  ),
  27 =>
  array(
    'matinhBNV' => 28,
    'matinhTMS' => '713',
    'tentinhmoi' => 'Tỉnh Đồng Nai',
    'phuongxa' =>
    array(
      0 =>
      array(
        'maphuongxa' => 71301001,
        'tenphuongxa' => 'Phường Biên Hoà',
      ),
      1 =>
      array(
        'maphuongxa' => 71301002,
        'tenphuongxa' => 'Phường Trấn Biên',
      ),
      2 =>
      array(
        'maphuongxa' => 71301003,
        'tenphuongxa' => 'Phường Tam Hiệp',
      ),
      3 =>
      array(
        'maphuongxa' => 71301004,
        'tenphuongxa' => 'Phường Long Bình',
      ),
      4 =>
      array(
        'maphuongxa' => 71301005,
        'tenphuongxa' => 'Phường Trảng Dài',
      ),
      5 =>
      array(
        'maphuongxa' => 71301006,
        'tenphuongxa' => 'Phường Hố Nai',
      ),
      6 =>
      array(
        'maphuongxa' => 71301007,
        'tenphuongxa' => 'Phường Long Hưng',
      ),
      7 =>
      array(
        'maphuongxa' => 71317008,
        'tenphuongxa' => 'Xã Đại Phước',
      ),
      8 =>
      array(
        'maphuongxa' => 71317009,
        'tenphuongxa' => 'Xã Nhơn Trạch',
      ),
      9 =>
      array(
        'maphuongxa' => 71317010,
        'tenphuongxa' => 'Xã Phước An',
      ),
      10 =>
      array(
        'maphuongxa' => 71315011,
        'tenphuongxa' => 'Xã Phước Thái',
      ),
      11 =>
      array(
        'maphuongxa' => 71315012,
        'tenphuongxa' => 'Xã Long Phước',
      ),
      12 =>
      array(
        'maphuongxa' => 71315013,
        'tenphuongxa' => 'Xã Bình An',
      ),
      13 =>
      array(
        'maphuongxa' => 71315014,
        'tenphuongxa' => 'Xã Long Thành',
      ),
      14 =>
      array(
        'maphuongxa' => 71315015,
        'tenphuongxa' => 'Xã An Phước',
      ),
      15 =>
      array(
        'maphuongxa' => 71308016,
        'tenphuongxa' => 'Xã An Viễn',
      ),
      16 =>
      array(
        'maphuongxa' => 71308017,
        'tenphuongxa' => 'Xã Bình Minh',
      ),
      17 =>
      array(
        'maphuongxa' => 71308018,
        'tenphuongxa' => 'Xã Trảng Bom',
      ),
      18 =>
      array(
        'maphuongxa' => 71308019,
        'tenphuongxa' => 'Xã Bàu Hàm',
      ),
      19 =>
      array(
        'maphuongxa' => 71308020,
        'tenphuongxa' => 'Xã Hưng Thịnh',
      ),
      20 =>
      array(
        'maphuongxa' => 71309021,
        'tenphuongxa' => 'Xã Dầu Giây',
      ),
      21 =>
      array(
        'maphuongxa' => 71309022,
        'tenphuongxa' => 'Xã Gia Kiệm',
      ),
      22 =>
      array(
        'maphuongxa' => 71305023,
        'tenphuongxa' => 'Xã Thống Nhất',
      ),
      23 =>
      array(
        'maphuongxa' => 71302024,
        'tenphuongxa' => 'Phường Bình Lộc',
      ),
      24 =>
      array(
        'maphuongxa' => 71302025,
        'tenphuongxa' => 'Phường Bảo Vinh',
      ),
      25 =>
      array(
        'maphuongxa' => 71302026,
        'tenphuongxa' => 'Phường Xuân Lập',
      ),
      26 =>
      array(
        'maphuongxa' => 71302027,
        'tenphuongxa' => 'Phường Long Khánh',
      ),
      27 =>
      array(
        'maphuongxa' => 71302028,
        'tenphuongxa' => 'Phường Hàng Gòn',
      ),
      28 =>
      array(
        'maphuongxa' => 71311029,
        'tenphuongxa' => 'Xã Xuân Quế',
      ),
      29 =>
      array(
        'maphuongxa' => 71311030,
        'tenphuongxa' => 'Xã Xuân Đường',
      ),
      30 =>
      array(
        'maphuongxa' => 71311031,
        'tenphuongxa' => 'Xã Cẩm Mỹ',
      ),
      31 =>
      array(
        'maphuongxa' => 71311032,
        'tenphuongxa' => 'Xã Sông Ray',
      ),
      32 =>
      array(
        'maphuongxa' => 71311033,
        'tenphuongxa' => 'Xã Xuân Đông',
      ),
      33 =>
      array(
        'maphuongxa' => 71313034,
        'tenphuongxa' => 'Xã Xuân Định',
      ),
      34 =>
      array(
        'maphuongxa' => 71313035,
        'tenphuongxa' => 'Xã Xuân Phú',
      ),
      35 =>
      array(
        'maphuongxa' => 71313036,
        'tenphuongxa' => 'Xã Xuân Lộc',
      ),
      36 =>
      array(
        'maphuongxa' => 71313037,
        'tenphuongxa' => 'Xã Xuân Hoà',
      ),
      37 =>
      array(
        'maphuongxa' => 71313038,
        'tenphuongxa' => 'Xã Xuân Thành',
      ),
      38 =>
      array(
        'maphuongxa' => 71313039,
        'tenphuongxa' => 'Xã Xuân Bắc',
      ),
      39 =>
      array(
        'maphuongxa' => 71305040,
        'tenphuongxa' => 'Xã La Ngà',
      ),
      40 =>
      array(
        'maphuongxa' => 71305041,
        'tenphuongxa' => 'Xã Định Quán',
      ),
      41 =>
      array(
        'maphuongxa' => 71305042,
        'tenphuongxa' => 'Xã Phú Vinh',
      ),
      42 =>
      array(
        'maphuongxa' => 71305043,
        'tenphuongxa' => 'Xã Phú Hoà',
      ),
      43 =>
      array(
        'maphuongxa' => 71303044,
        'tenphuongxa' => 'Xã Tà Lài',
      ),
      44 =>
      array(
        'maphuongxa' => 71303045,
        'tenphuongxa' => 'Xã Nam Cát Tiên',
      ),
      45 =>
      array(
        'maphuongxa' => 71303046,
        'tenphuongxa' => 'Xã Tân Phú',
      ),
      46 =>
      array(
        'maphuongxa' => 71303047,
        'tenphuongxa' => 'Xã Phú Lâm',
      ),
      47 =>
      array(
        'maphuongxa' => 71307048,
        'tenphuongxa' => 'Xã Trị An',
      ),
      48 =>
      array(
        'maphuongxa' => 71307049,
        'tenphuongxa' => 'Xã Tân An',
      ),
      49 =>
      array(
        'maphuongxa' => 71307050,
        'tenphuongxa' => 'Phường Tân Triều',
      ),
      50 =>
      array(
        'maphuongxa' => 70710051,
        'tenphuongxa' => 'Phường Minh Hưng',
      ),
      51 =>
      array(
        'maphuongxa' => 70710052,
        'tenphuongxa' => 'Phường Chơn Thành',
      ),
      52 =>
      array(
        'maphuongxa' => 70710053,
        'tenphuongxa' => 'Xã Nha Bích',
      ),
      53 =>
      array(
        'maphuongxa' => 70713054,
        'tenphuongxa' => 'Xã Tân Quan',
      ),
      54 =>
      array(
        'maphuongxa' => 70713055,
        'tenphuongxa' => 'Xã Tân Hưng',
      ),
      55 =>
      array(
        'maphuongxa' => 70713056,
        'tenphuongxa' => 'Xã Tân Khai',
      ),
      56 =>
      array(
        'maphuongxa' => 70713057,
        'tenphuongxa' => 'Xã Minh Đức',
      ),
      57 =>
      array(
        'maphuongxa' => 70709058,
        'tenphuongxa' => 'Phường Bình Long',
      ),
      58 =>
      array(
        'maphuongxa' => 70709059,
        'tenphuongxa' => 'Phường An Lộc',
      ),
      59 =>
      array(
        'maphuongxa' => 70705060,
        'tenphuongxa' => 'Xã Lộc Thành',
      ),
      60 =>
      array(
        'maphuongxa' => 70705061,
        'tenphuongxa' => 'Xã Lộc Ninh',
      ),
      61 =>
      array(
        'maphuongxa' => 70705062,
        'tenphuongxa' => 'Xã Lộc Hưng',
      ),
      62 =>
      array(
        'maphuongxa' => 70705063,
        'tenphuongxa' => 'Xã Lộc Tấn',
      ),
      63 =>
      array(
        'maphuongxa' => 70705064,
        'tenphuongxa' => 'Xã Lộc Thạnh',
      ),
      64 =>
      array(
        'maphuongxa' => 70705065,
        'tenphuongxa' => 'Xã Lộc Quang',
      ),
      65 =>
      array(
        'maphuongxa' => 70706066,
        'tenphuongxa' => 'Xã Tân Tiến',
      ),
      66 =>
      array(
        'maphuongxa' => 70706067,
        'tenphuongxa' => 'Xã Thiện Hưng',
      ),
      67 =>
      array(
        'maphuongxa' => 70706068,
        'tenphuongxa' => 'Xã Hưng Phước',
      ),
      68 =>
      array(
        'maphuongxa' => 70715069,
        'tenphuongxa' => 'Xã Phú Nghĩa',
      ),
      69 =>
      array(
        'maphuongxa' => 70715070,
        'tenphuongxa' => 'Xã Đa Kia',
      ),
      70 =>
      array(
        'maphuongxa' => 70703071,
        'tenphuongxa' => 'Phường Phước Bình',
      ),
      71 =>
      array(
        'maphuongxa' => 70703072,
        'tenphuongxa' => 'Phường Phước Long',
      ),
      72 =>
      array(
        'maphuongxa' => 70716073,
        'tenphuongxa' => 'Xã Bình Tân',
      ),
      73 =>
      array(
        'maphuongxa' => 70716074,
        'tenphuongxa' => 'Xã Long Hà',
      ),
      74 =>
      array(
        'maphuongxa' => 70716075,
        'tenphuongxa' => 'Xã Phú Riềng',
      ),
      75 =>
      array(
        'maphuongxa' => 70716076,
        'tenphuongxa' => 'Xã Phú Trung',
      ),
      76 =>
      array(
        'maphuongxa' => 70711077,
        'tenphuongxa' => 'Phường Đồng Xoài',
      ),
      77 =>
      array(
        'maphuongxa' => 70711078,
        'tenphuongxa' => 'Phường Bình Phước',
      ),
      78 =>
      array(
        'maphuongxa' => 70701079,
        'tenphuongxa' => 'Xã Thuận Lợi',
      ),
      79 =>
      array(
        'maphuongxa' => 70701080,
        'tenphuongxa' => 'Xã Đồng Tâm',
      ),
      80 =>
      array(
        'maphuongxa' => 70701081,
        'tenphuongxa' => 'Xã Tân Lợi',
      ),
      81 =>
      array(
        'maphuongxa' => 70701082,
        'tenphuongxa' => 'Xã Đồng Phú',
      ),
      82 =>
      array(
        'maphuongxa' => 70707083,
        'tenphuongxa' => 'Xã Phước Sơn',
      ),
      83 =>
      array(
        'maphuongxa' => 70707084,
        'tenphuongxa' => 'Xã Nghĩa Trung',
      ),
      84 =>
      array(
        'maphuongxa' => 70707085,
        'tenphuongxa' => 'Xã Bù Đăng',
      ),
      85 =>
      array(
        'maphuongxa' => 70707086,
        'tenphuongxa' => 'Xã Thọ Sơn',
      ),
      86 =>
      array(
        'maphuongxa' => 70707087,
        'tenphuongxa' => 'Xã Đak Nhau',
      ),
      87 =>
      array(
        'maphuongxa' => 70707088,
        'tenphuongxa' => 'Xã Bom Bo',
      ),
      88 =>
      array(
        'maphuongxa' => 71301089,
        'tenphuongxa' => 'Phường Tam Phước',
      ),
      89 =>
      array(
        'maphuongxa' => 71301090,
        'tenphuongxa' => 'Phường Phước Tân',
      ),
      90 =>
      array(
        'maphuongxa' => 71305091,
        'tenphuongxa' => 'Xã Thanh Sơn',
      ),
      91 =>
      array(
        'maphuongxa' => 71303092,
        'tenphuongxa' => 'Xã Đak Lua',
      ),
      92 =>
      array(
        'maphuongxa' => 71307093,
        'tenphuongxa' => 'Xã Phú Lý',
      ),
      93 =>
      array(
        'maphuongxa' => 70715094,
        'tenphuongxa' => 'Xã Bù Gia Mập',
      ),
      94 =>
      array(
        'maphuongxa' => 70715095,
        'tenphuongxa' => 'Xã Đăk Ơ',
      ),
    ),
  ),
  28 =>
  array(
    'matinhBNV' => 29,
    'matinhTMS' => '701',
    'tentinhmoi' => 'Thành Phố Hồ Chí Minh',
    'phuongxa' =>
    array(
      0 =>
      array(
        'maphuongxa' => 71701001,
        'tenphuongxa' => 'Phường Vũng Tàu',
      ),
      1 =>
      array(
        'maphuongxa' => 71701002,
        'tenphuongxa' => 'Phường Tam Thắng',
      ),
      2 =>
      array(
        'maphuongxa' => 71701003,
        'tenphuongxa' => 'Phường Rạch Dừa',
      ),
      3 =>
      array(
        'maphuongxa' => 71701004,
        'tenphuongxa' => 'Phường Phước Thắng',
      ),
      4 =>
      array(
        'maphuongxa' => 71703005,
        'tenphuongxa' => 'Phường Bà Rịa',
      ),
      5 =>
      array(
        'maphuongxa' => 71703006,
        'tenphuongxa' => 'Phường Long Hương',
      ),
      6 =>
      array(
        'maphuongxa' => 71709007,
        'tenphuongxa' => 'Phường Phú Mỹ',
      ),
      7 =>
      array(
        'maphuongxa' => 71703008,
        'tenphuongxa' => 'Phường Tam Long',
      ),
      8 =>
      array(
        'maphuongxa' => 71709009,
        'tenphuongxa' => 'Phường Tân Thành',
      ),
      9 =>
      array(
        'maphuongxa' => 71709010,
        'tenphuongxa' => 'Phường Tân Phước',
      ),
      10 =>
      array(
        'maphuongxa' => 71709011,
        'tenphuongxa' => 'Phường Tân Hải',
      ),
      11 =>
      array(
        'maphuongxa' => 71709012,
        'tenphuongxa' => 'Xã Châu Pha',
      ),
      12 =>
      array(
        'maphuongxa' => 71705013,
        'tenphuongxa' => 'Xã Ngãi Giao',
      ),
      13 =>
      array(
        'maphuongxa' => 71705014,
        'tenphuongxa' => 'Xã Bình Giã',
      ),
      14 =>
      array(
        'maphuongxa' => 71705015,
        'tenphuongxa' => 'Xã Kim Long',
      ),
      15 =>
      array(
        'maphuongxa' => 71705016,
        'tenphuongxa' => 'Xã Châu Đức',
      ),
      16 =>
      array(
        'maphuongxa' => 71705017,
        'tenphuongxa' => 'Xã Xuân Sơn',
      ),
      17 =>
      array(
        'maphuongxa' => 71705018,
        'tenphuongxa' => 'Xã Nghĩa Thành',
      ),
      18 =>
      array(
        'maphuongxa' => 71707019,
        'tenphuongxa' => 'Xã Hồ Tràm',
      ),
      19 =>
      array(
        'maphuongxa' => 71707020,
        'tenphuongxa' => 'Xã Xuyên Mộc',
      ),
      20 =>
      array(
        'maphuongxa' => 71707021,
        'tenphuongxa' => 'Xã Hòa Hội',
      ),
      21 =>
      array(
        'maphuongxa' => 71707022,
        'tenphuongxa' => 'Xã Bàu Lâm',
      ),
      22 =>
      array(
        'maphuongxa' => 71712023,
        'tenphuongxa' => 'Xã Phước Hải',
      ),
      23 =>
      array(
        'maphuongxa' => 71712024,
        'tenphuongxa' => 'Xã Long Hải',
      ),
      24 =>
      array(
        'maphuongxa' => 71712025,
        'tenphuongxa' => 'Xã Đất Đỏ',
      ),
      25 =>
      array(
        'maphuongxa' => 71712026,
        'tenphuongxa' => 'Xã Long Điền',
      ),
      26 =>
      array(
        'maphuongxa' => 71713027,
        'tenphuongxa' => 'Đặc khu Côn Đảo',
      ),
      27 =>
      array(
        'maphuongxa' => 71109028,
        'tenphuongxa' => 'Phường Đông Hoà',
      ),
      28 =>
      array(
        'maphuongxa' => 71109029,
        'tenphuongxa' => 'Phường Dĩ An',
      ),
      29 =>
      array(
        'maphuongxa' => 71109030,
        'tenphuongxa' => 'Phường Tân Đông Hiệp',
      ),
      30 =>
      array(
        'maphuongxa' => 71107031,
        'tenphuongxa' => 'Phường Thuận An',
      ),
      31 =>
      array(
        'maphuongxa' => 71107032,
        'tenphuongxa' => 'Phường Thuận Giao',
      ),
      32 =>
      array(
        'maphuongxa' => 71107033,
        'tenphuongxa' => 'Phường Bình Hoà',
      ),
      33 =>
      array(
        'maphuongxa' => 71107034,
        'tenphuongxa' => 'Phường Lái Thiêu',
      ),
      34 =>
      array(
        'maphuongxa' => 71107035,
        'tenphuongxa' => 'Phường An Phú',
      ),
      35 =>
      array(
        'maphuongxa' => 71101036,
        'tenphuongxa' => 'Phường Bình Dương',
      ),
      36 =>
      array(
        'maphuongxa' => 71101037,
        'tenphuongxa' => 'Phường Chánh Hiệp',
      ),
      37 =>
      array(
        'maphuongxa' => 71101038,
        'tenphuongxa' => 'Phường Thủ Dầu Một',
      ),
      38 =>
      array(
        'maphuongxa' => 71101039,
        'tenphuongxa' => 'Phường Phú Lợi',
      ),
      39 =>
      array(
        'maphuongxa' => 71105040,
        'tenphuongxa' => 'Phường Vĩnh Tân',
      ),
      40 =>
      array(
        'maphuongxa' => 71105041,
        'tenphuongxa' => 'Phường Bình Cơ',
      ),
      41 =>
      array(
        'maphuongxa' => 71105042,
        'tenphuongxa' => 'Phường Tân Uyên',
      ),
      42 =>
      array(
        'maphuongxa' => 71105043,
        'tenphuongxa' => 'Phường Tân Hiệp',
      ),
      43 =>
      array(
        'maphuongxa' => 71105044,
        'tenphuongxa' => 'Phường Tân Khánh',
      ),
      44 =>
      array(
        'maphuongxa' => 71103045,
        'tenphuongxa' => 'Phường Hoà Lợi',
      ),
      45 =>
      array(
        'maphuongxa' => 71101046,
        'tenphuongxa' => 'Phường Phú An',
      ),
      46 =>
      array(
        'maphuongxa' => 71113047,
        'tenphuongxa' => 'Phường Tây Nam',
      ),
      47 =>
      array(
        'maphuongxa' => 71115048,
        'tenphuongxa' => 'Phường Long Nguyên',
      ),
      48 =>
      array(
        'maphuongxa' => 71115049,
        'tenphuongxa' => 'Phường Bến Cát',
      ),
      49 =>
      array(
        'maphuongxa' => 71115050,
        'tenphuongxa' => 'Phường Chánh Phú Hoà',
      ),
      50 =>
      array(
        'maphuongxa' => 71117051,
        'tenphuongxa' => 'Xã Bắc Tân Uyên',
      ),
      51 =>
      array(
        'maphuongxa' => 71117052,
        'tenphuongxa' => 'Xã Thường Tân',
      ),
      52 =>
      array(
        'maphuongxa' => 71111053,
        'tenphuongxa' => 'Xã An Long',
      ),
      53 =>
      array(
        'maphuongxa' => 71111054,
        'tenphuongxa' => 'Xã Phước Thành',
      ),
      54 =>
      array(
        'maphuongxa' => 71111055,
        'tenphuongxa' => 'Xã Phước Hoà',
      ),
      55 =>
      array(
        'maphuongxa' => 71111056,
        'tenphuongxa' => 'Xã Phú Giáo',
      ),
      56 =>
      array(
        'maphuongxa' => 71115057,
        'tenphuongxa' => 'Xã Trừ Văn Thố',
      ),
      57 =>
      array(
        'maphuongxa' => 71115058,
        'tenphuongxa' => 'Xã Bàu Bàng',
      ),
      58 =>
      array(
        'maphuongxa' => 71113059,
        'tenphuongxa' => 'Xã Minh Thạnh',
      ),
      59 =>
      array(
        'maphuongxa' => 71113060,
        'tenphuongxa' => 'Xã Long Hoà',
      ),
      60 =>
      array(
        'maphuongxa' => 71113061,
        'tenphuongxa' => 'Xã Dầu Tiếng',
      ),
      61 =>
      array(
        'maphuongxa' => 71113062,
        'tenphuongxa' => 'Xã Thanh An',
      ),
      62 =>
      array(
        'maphuongxa' => 70101063,
        'tenphuongxa' => 'Phường Sài Gòn',
      ),
      63 =>
      array(
        'maphuongxa' => 70101064,
        'tenphuongxa' => 'Phường Tân Định',
      ),
      64 =>
      array(
        'maphuongxa' => 70101065,
        'tenphuongxa' => 'Phường Bến Thành',
      ),
      65 =>
      array(
        'maphuongxa' => 70101066,
        'tenphuongxa' => 'Phường Cầu Ông Lãnh',
      ),
      66 =>
      array(
        'maphuongxa' => 70105067,
        'tenphuongxa' => 'Phường Bàn Cờ',
      ),
      67 =>
      array(
        'maphuongxa' => 70105068,
        'tenphuongxa' => 'Phường Xuân Hoà',
      ),
      68 =>
      array(
        'maphuongxa' => 70105069,
        'tenphuongxa' => 'Phường Nhiêu Lộc',
      ),
      69 =>
      array(
        'maphuongxa' => 70107070,
        'tenphuongxa' => 'Phường Xóm Chiếu',
      ),
      70 =>
      array(
        'maphuongxa' => 70107071,
        'tenphuongxa' => 'Phường Khánh Hội',
      ),
      71 =>
      array(
        'maphuongxa' => 70107072,
        'tenphuongxa' => 'Phường Vĩnh Hội',
      ),
      72 =>
      array(
        'maphuongxa' => 70109073,
        'tenphuongxa' => 'Phường Chợ Quán',
      ),
      73 =>
      array(
        'maphuongxa' => 70109074,
        'tenphuongxa' => 'Phường An Đông',
      ),
      74 =>
      array(
        'maphuongxa' => 70109075,
        'tenphuongxa' => 'Phường Chợ Lớn',
      ),
      75 =>
      array(
        'maphuongxa' => 70111076,
        'tenphuongxa' => 'Phường Bình Tây',
      ),
      76 =>
      array(
        'maphuongxa' => 70111077,
        'tenphuongxa' => 'Phường Bình Tiên',
      ),
      77 =>
      array(
        'maphuongxa' => 70111078,
        'tenphuongxa' => 'Phường Bình Phú',
      ),
      78 =>
      array(
        'maphuongxa' => 70111079,
        'tenphuongxa' => 'Phường Phú Lâm',
      ),
      79 =>
      array(
        'maphuongxa' => 70113080,
        'tenphuongxa' => 'Phường Tân Thuận',
      ),
      80 =>
      array(
        'maphuongxa' => 70113081,
        'tenphuongxa' => 'Phường Phú Thuận',
      ),
      81 =>
      array(
        'maphuongxa' => 70113082,
        'tenphuongxa' => 'Phường Tân Mỹ',
      ),
      82 =>
      array(
        'maphuongxa' => 70113083,
        'tenphuongxa' => 'Phường Tân Hưng',
      ),
      83 =>
      array(
        'maphuongxa' => 70115084,
        'tenphuongxa' => 'Phường Chánh Hưng',
      ),
      84 =>
      array(
        'maphuongxa' => 70115085,
        'tenphuongxa' => 'Phường Phú Định',
      ),
      85 =>
      array(
        'maphuongxa' => 70115086,
        'tenphuongxa' => 'Phường Bình Đông',
      ),
      86 =>
      array(
        'maphuongxa' => 70119087,
        'tenphuongxa' => 'Phường Diên Hồng',
      ),
      87 =>
      array(
        'maphuongxa' => 70119088,
        'tenphuongxa' => 'Phường Vườn Lài',
      ),
      88 =>
      array(
        'maphuongxa' => 70119089,
        'tenphuongxa' => 'Phường Hoà Hưng',
      ),
      89 =>
      array(
        'maphuongxa' => 70121090,
        'tenphuongxa' => 'Phường Minh Phụng',
      ),
      90 =>
      array(
        'maphuongxa' => 70121091,
        'tenphuongxa' => 'Phường Bình Thới',
      ),
      91 =>
      array(
        'maphuongxa' => 70121092,
        'tenphuongxa' => 'Phường Hoà Bình',
      ),
      92 =>
      array(
        'maphuongxa' => 70121093,
        'tenphuongxa' => 'Phường Phú Thọ',
      ),
      93 =>
      array(
        'maphuongxa' => 70123094,
        'tenphuongxa' => 'Phường Đông Hưng Thuận',
      ),
      94 =>
      array(
        'maphuongxa' => 70123095,
        'tenphuongxa' => 'Phường Trung Mỹ Tây',
      ),
      95 =>
      array(
        'maphuongxa' => 70123096,
        'tenphuongxa' => 'Phường Tân Thới Hiệp',
      ),
      96 =>
      array(
        'maphuongxa' => 70123097,
        'tenphuongxa' => 'Phường Thới An',
      ),
      97 =>
      array(
        'maphuongxa' => 70123098,
        'tenphuongxa' => 'Phường An Phú Đông',
      ),
      98 =>
      array(
        'maphuongxa' => 70134099,
        'tenphuongxa' => 'Phường An Lạc',
      ),
      99 =>
      array(
        'maphuongxa' => 70134100,
        'tenphuongxa' => 'Phường Tân Tạo',
      ),
      100 =>
      array(
        'maphuongxa' => 70134101,
        'tenphuongxa' => 'Phường Bình Tân',
      ),
      101 =>
      array(
        'maphuongxa' => 70134102,
        'tenphuongxa' => 'Phường Bình Trị Đông',
      ),
      102 =>
      array(
        'maphuongxa' => 70134103,
        'tenphuongxa' => 'Phường Bình Hưng Hoà',
      ),
      103 =>
      array(
        'maphuongxa' => 70129104,
        'tenphuongxa' => 'Phường Gia Định',
      ),
      104 =>
      array(
        'maphuongxa' => 70129105,
        'tenphuongxa' => 'Phường Bình Thạnh',
      ),
      105 =>
      array(
        'maphuongxa' => 70129106,
        'tenphuongxa' => 'Phường Bình Lợi Trung',
      ),
      106 =>
      array(
        'maphuongxa' => 70129107,
        'tenphuongxa' => 'Phường Thạnh Mỹ Tây',
      ),
      107 =>
      array(
        'maphuongxa' => 70129108,
        'tenphuongxa' => 'Phường Bình Quới',
      ),
      108 =>
      array(
        'maphuongxa' => 70125109,
        'tenphuongxa' => 'Phường Hạnh Thông',
      ),
      109 =>
      array(
        'maphuongxa' => 70125110,
        'tenphuongxa' => 'Phường An Nhơn',
      ),
      110 =>
      array(
        'maphuongxa' => 70125111,
        'tenphuongxa' => 'Phường Gò Vấp',
      ),
      111 =>
      array(
        'maphuongxa' => 70125112,
        'tenphuongxa' => 'Phường An Hội Đông',
      ),
      112 =>
      array(
        'maphuongxa' => 70125113,
        'tenphuongxa' => 'Phường Thông Tây Hội',
      ),
      113 =>
      array(
        'maphuongxa' => 70125114,
        'tenphuongxa' => 'Phường An Hội Tây',
      ),
      114 =>
      array(
        'maphuongxa' => 70131115,
        'tenphuongxa' => 'Phường Đức Nhuận',
      ),
      115 =>
      array(
        'maphuongxa' => 70131116,
        'tenphuongxa' => 'Phường Cầu Kiệu',
      ),
      116 =>
      array(
        'maphuongxa' => 70131117,
        'tenphuongxa' => 'Phường Phú Nhuận',
      ),
      117 =>
      array(
        'maphuongxa' => 70127118,
        'tenphuongxa' => 'Phường Tân Sơn Hoà',
      ),
      118 =>
      array(
        'maphuongxa' => 70127119,
        'tenphuongxa' => 'Phường Tân Sơn Nhất',
      ),
      119 =>
      array(
        'maphuongxa' => 70127120,
        'tenphuongxa' => 'Phường Tân Hoà',
      ),
      120 =>
      array(
        'maphuongxa' => 70127121,
        'tenphuongxa' => 'Phường Bảy Hiền',
      ),
      121 =>
      array(
        'maphuongxa' => 70127122,
        'tenphuongxa' => 'Phường Tân Bình',
      ),
      122 =>
      array(
        'maphuongxa' => 70127123,
        'tenphuongxa' => 'Phường Tân Sơn',
      ),
      123 =>
      array(
        'maphuongxa' => 70128124,
        'tenphuongxa' => 'Phường Tây Thạnh',
      ),
      124 =>
      array(
        'maphuongxa' => 70128125,
        'tenphuongxa' => 'Phường Tân Sơn Nhì',
      ),
      125 =>
      array(
        'maphuongxa' => 70128126,
        'tenphuongxa' => 'Phường Phú Thọ Hoà',
      ),
      126 =>
      array(
        'maphuongxa' => 70128127,
        'tenphuongxa' => 'Phường Tân Phú',
      ),
      127 =>
      array(
        'maphuongxa' => 70128128,
        'tenphuongxa' => 'Phường Phú Thạnh',
      ),
      128 =>
      array(
        'maphuongxa' => 70145129,
        'tenphuongxa' => 'Phường Hiệp Bình',
      ),
      129 =>
      array(
        'maphuongxa' => 70145130,
        'tenphuongxa' => 'Phường Thủ Đức',
      ),
      130 =>
      array(
        'maphuongxa' => 70145131,
        'tenphuongxa' => 'Phường Tam Bình',
      ),
      131 =>
      array(
        'maphuongxa' => 70145132,
        'tenphuongxa' => 'Phường Linh Xuân',
      ),
      132 =>
      array(
        'maphuongxa' => 70145133,
        'tenphuongxa' => 'Phường Tăng Nhơn Phú',
      ),
      133 =>
      array(
        'maphuongxa' => 70145134,
        'tenphuongxa' => 'Phường Long Bình',
      ),
      134 =>
      array(
        'maphuongxa' => 70145135,
        'tenphuongxa' => 'Phường Long Phước',
      ),
      135 =>
      array(
        'maphuongxa' => 70145136,
        'tenphuongxa' => 'Phường Long Trường',
      ),
      136 =>
      array(
        'maphuongxa' => 70145137,
        'tenphuongxa' => 'Phường Cát Lái',
      ),
      137 =>
      array(
        'maphuongxa' => 70145138,
        'tenphuongxa' => 'Phường Bình Trưng',
      ),
      138 =>
      array(
        'maphuongxa' => 70145139,
        'tenphuongxa' => 'Phường Phước Long',
      ),
      139 =>
      array(
        'maphuongxa' => 70145140,
        'tenphuongxa' => 'Phường An Khánh',
      ),
      140 =>
      array(
        'maphuongxa' => 70139141,
        'tenphuongxa' => 'Xã Vĩnh Lộc',
      ),
      141 =>
      array(
        'maphuongxa' => 70139142,
        'tenphuongxa' => 'Xã Tân Vĩnh Lộc',
      ),
      142 =>
      array(
        'maphuongxa' => 70139143,
        'tenphuongxa' => 'Xã Bình Lợi',
      ),
      143 =>
      array(
        'maphuongxa' => 70139144,
        'tenphuongxa' => 'Xã Tân Nhựt',
      ),
      144 =>
      array(
        'maphuongxa' => 70139145,
        'tenphuongxa' => 'Xã Bình Chánh',
      ),
      145 =>
      array(
        'maphuongxa' => 70139146,
        'tenphuongxa' => 'Xã Hưng Long',
      ),
      146 =>
      array(
        'maphuongxa' => 70139147,
        'tenphuongxa' => 'Xã Bình Hưng',
      ),
      147 =>
      array(
        'maphuongxa' => 70143148,
        'tenphuongxa' => 'Xã Bình Khánh',
      ),
      148 =>
      array(
        'maphuongxa' => 70143149,
        'tenphuongxa' => 'Xã An Thới Đông',
      ),
      149 =>
      array(
        'maphuongxa' => 70143150,
        'tenphuongxa' => 'Xã Cần Giờ',
      ),
      150 =>
      array(
        'maphuongxa' => 70135151,
        'tenphuongxa' => 'Xã Củ Chi',
      ),
      151 =>
      array(
        'maphuongxa' => 70135152,
        'tenphuongxa' => 'Xã Tân An Hội',
      ),
      152 =>
      array(
        'maphuongxa' => 70135153,
        'tenphuongxa' => 'Xã Thái Mỹ',
      ),
      153 =>
      array(
        'maphuongxa' => 70135154,
        'tenphuongxa' => 'Xã An Nhơn Tây',
      ),
      154 =>
      array(
        'maphuongxa' => 70135155,
        'tenphuongxa' => 'Xã Nhuận Đức',
      ),
      155 =>
      array(
        'maphuongxa' => 70135156,
        'tenphuongxa' => 'Xã Phú Hoà Đông',
      ),
      156 =>
      array(
        'maphuongxa' => 70135157,
        'tenphuongxa' => 'Xã Bình Mỹ',
      ),
      157 =>
      array(
        'maphuongxa' => 70137158,
        'tenphuongxa' => 'Xã Đông Thạnh',
      ),
      158 =>
      array(
        'maphuongxa' => 70137159,
        'tenphuongxa' => 'Xã Hóc Môn',
      ),
      159 =>
      array(
        'maphuongxa' => 70137160,
        'tenphuongxa' => 'Xã Xuân Thới Sơn',
      ),
      160 =>
      array(
        'maphuongxa' => 70137161,
        'tenphuongxa' => 'Xã Bà Điểm',
      ),
      161 =>
      array(
        'maphuongxa' => 70141162,
        'tenphuongxa' => 'Xã Nhà Bè',
      ),
      162 =>
      array(
        'maphuongxa' => 70141163,
        'tenphuongxa' => 'Xã Hiệp Phước',
      ),
      163 =>
      array(
        'maphuongxa' => 71701164,
        'tenphuongxa' => 'Xã Long Sơn',
      ),
      164 =>
      array(
        'maphuongxa' => 71707165,
        'tenphuongxa' => 'Xã Hòa Hiệp',
      ),
      165 =>
      array(
        'maphuongxa' => 71707166,
        'tenphuongxa' => 'Xã Bình Châu',
      ),
      166 =>
      array(
        'maphuongxa' => 71103167,
        'tenphuongxa' => 'Phường Thới Hoà',
      ),
      167 =>
      array(
        'maphuongxa' => 70143168,
        'tenphuongxa' => 'Xã Thạnh An',
      ),
    ),
  ),
  29 =>
  array(
    'matinhBNV' => 30,
    'matinhTMS' => '809',
    'tentinhmoi' => 'Tỉnh Vĩnh Long',
    'phuongxa' =>
    array(
      0 =>
      array(
        'maphuongxa' => 81701037,
        'tenphuongxa' => 'Phường Trà Vinh',
      ),
      1 =>
      array(
        'maphuongxa' => 80905001,
        'tenphuongxa' => 'Xã Cái Nhum',
      ),
      2 =>
      array(
        'maphuongxa' => 81701036,
        'tenphuongxa' => 'Phường Long Đức',
      ),
      3 =>
      array(
        'maphuongxa' => 80905002,
        'tenphuongxa' => 'Xã Tân Long Hội',
      ),
      4 =>
      array(
        'maphuongxa' => 81701038,
        'tenphuongxa' => 'Phường Nguyệt Hóa',
      ),
      5 =>
      array(
        'maphuongxa' => 80905003,
        'tenphuongxa' => 'Xã Nhơn Phú',
      ),
      6 =>
      array(
        'maphuongxa' => 81701039,
        'tenphuongxa' => 'Phường Hòa Thuận',
      ),
      7 =>
      array(
        'maphuongxa' => 80905004,
        'tenphuongxa' => 'Xã Bình Phước',
      ),
      8 =>
      array(
        'maphuongxa' => 81703042,
        'tenphuongxa' => 'Xã Càng Long',
      ),
      9 =>
      array(
        'maphuongxa' => 80903005,
        'tenphuongxa' => 'Xã An Bình',
      ),
      10 =>
      array(
        'maphuongxa' => 81703040,
        'tenphuongxa' => 'Xã An Trường',
      ),
      11 =>
      array(
        'maphuongxa' => 80903006,
        'tenphuongxa' => 'Xã Long Hồ',
      ),
      12 =>
      array(
        'maphuongxa' => 81703041,
        'tenphuongxa' => 'Xã Tân An',
      ),
      13 =>
      array(
        'maphuongxa' => 80903007,
        'tenphuongxa' => 'Xã Phú Quới',
      ),
      14 =>
      array(
        'maphuongxa' => 81703043,
        'tenphuongxa' => 'Xã Nhị Long',
      ),
      15 =>
      array(
        'maphuongxa' => 80901008,
        'tenphuongxa' => 'Phường Thanh Đức',
      ),
      16 =>
      array(
        'maphuongxa' => 81703044,
        'tenphuongxa' => 'Xã Bình Phú',
      ),
      17 =>
      array(
        'maphuongxa' => 80901009,
        'tenphuongxa' => 'Phường Long Châu',
      ),
      18 =>
      array(
        'maphuongxa' => 81705046,
        'tenphuongxa' => 'Xã Châu Thành',
      ),
      19 =>
      array(
        'maphuongxa' => 80901010,
        'tenphuongxa' => 'Phường Phước Hậu',
      ),
      20 =>
      array(
        'maphuongxa' => 81705045,
        'tenphuongxa' => 'Xã Song Lộc',
      ),
      21 =>
      array(
        'maphuongxa' => 80901011,
        'tenphuongxa' => 'Phường Tân Hạnh',
      ),
      22 =>
      array(
        'maphuongxa' => 81705047,
        'tenphuongxa' => 'Xã Hưng Mỹ',
      ),
      23 =>
      array(
        'maphuongxa' => 80901012,
        'tenphuongxa' => 'Phường Tân Ngãi',
      ),
      24 =>
      array(
        'maphuongxa' => 81705048,
        'tenphuongxa' => 'Xã Hòa Minh',
      ),
      25 =>
      array(
        'maphuongxa' => 80913013,
        'tenphuongxa' => 'Xã Quới Thiện',
      ),
      26 =>
      array(
        'maphuongxa' => 81705049,
        'tenphuongxa' => 'Xã Long Hòa',
      ),
      27 =>
      array(
        'maphuongxa' => 80913014,
        'tenphuongxa' => 'Xã Trung Thành',
      ),
      28 =>
      array(
        'maphuongxa' => 81707050,
        'tenphuongxa' => 'Xã Cầu Kè',
      ),
      29 =>
      array(
        'maphuongxa' => 80913015,
        'tenphuongxa' => 'Xã Trung Ngãi',
      ),
      30 =>
      array(
        'maphuongxa' => 81707051,
        'tenphuongxa' => 'Xã Phong Thạnh',
      ),
      31 =>
      array(
        'maphuongxa' => 80913016,
        'tenphuongxa' => 'Xã Quới An',
      ),
      32 =>
      array(
        'maphuongxa' => 81707052,
        'tenphuongxa' => 'Xã An Phú Tân',
      ),
      33 =>
      array(
        'maphuongxa' => 80913017,
        'tenphuongxa' => 'Xã Trung Hiệp',
      ),
      34 =>
      array(
        'maphuongxa' => 81707053,
        'tenphuongxa' => 'Xã Tam Ngãi',
      ),
      35 =>
      array(
        'maphuongxa' => 80913018,
        'tenphuongxa' => 'Xã Hiếu Phụng',
      ),
      36 =>
      array(
        'maphuongxa' => 81709056,
        'tenphuongxa' => 'Xã Tiểu Cần',
      ),
      37 =>
      array(
        'maphuongxa' => 80913019,
        'tenphuongxa' => 'Xã Hiếu Thành',
      ),
      38 =>
      array(
        'maphuongxa' => 81709054,
        'tenphuongxa' => 'Xã Tân Hòa',
      ),
      39 =>
      array(
        'maphuongxa' => 80911020,
        'tenphuongxa' => 'Xã Lục Sỹ Thành',
      ),
      40 =>
      array(
        'maphuongxa' => 81709055,
        'tenphuongxa' => 'Xã Hùng Hòa',
      ),
      41 =>
      array(
        'maphuongxa' => 80911021,
        'tenphuongxa' => 'Xã Trà Ôn',
      ),
      42 =>
      array(
        'maphuongxa' => 81709057,
        'tenphuongxa' => 'Xã Tập Ngãi',
      ),
      43 =>
      array(
        'maphuongxa' => 80911022,
        'tenphuongxa' => 'Xã Trà Côn',
      ),
      44 =>
      array(
        'maphuongxa' => 81711060,
        'tenphuongxa' => 'Xã Cầu Ngang',
      ),
      45 =>
      array(
        'maphuongxa' => 80911023,
        'tenphuongxa' => 'Xã Vĩnh Xuân',
      ),
      46 =>
      array(
        'maphuongxa' => 81711058,
        'tenphuongxa' => 'Xã Mỹ Long',
      ),
      47 =>
      array(
        'maphuongxa' => 80911024,
        'tenphuongxa' => 'Xã Hòa Bình',
      ),
      48 =>
      array(
        'maphuongxa' => 81711059,
        'tenphuongxa' => 'Xã Vinh Kim',
      ),
      49 =>
      array(
        'maphuongxa' => 80909025,
        'tenphuongxa' => 'Xã Hòa Hiệp',
      ),
      50 =>
      array(
        'maphuongxa' => 81711061,
        'tenphuongxa' => 'Xã Nhị Trường',
      ),
      51 =>
      array(
        'maphuongxa' => 80909026,
        'tenphuongxa' => 'Xã Tam Bình',
      ),
      52 =>
      array(
        'maphuongxa' => 81711062,
        'tenphuongxa' => 'Xã Hiệp Mỹ',
      ),
      53 =>
      array(
        'maphuongxa' => 80909027,
        'tenphuongxa' => 'Xã Ngãi Tứ',
      ),
      54 =>
      array(
        'maphuongxa' => 81713066,
        'tenphuongxa' => 'Xã Trà Cú',
      ),
      55 =>
      array(
        'maphuongxa' => 80909028,
        'tenphuongxa' => 'Xã Song Phú',
      ),
      56 =>
      array(
        'maphuongxa' => 81713063,
        'tenphuongxa' => 'Xã Lưu Nghiệp Anh',
      ),
      57 =>
      array(
        'maphuongxa' => 80909029,
        'tenphuongxa' => 'Xã Cái Ngang',
      ),
      58 =>
      array(
        'maphuongxa' => 81713064,
        'tenphuongxa' => 'Xã Đại An',
      ),
      59 =>
      array(
        'maphuongxa' => 80908030,
        'tenphuongxa' => 'Xã Tân Quới',
      ),
      60 =>
      array(
        'maphuongxa' => 81713065,
        'tenphuongxa' => 'Xã Hàm Giang',
      ),
      61 =>
      array(
        'maphuongxa' => 80908031,
        'tenphuongxa' => 'Xã Tân Lược',
      ),
      62 =>
      array(
        'maphuongxa' => 81713067,
        'tenphuongxa' => 'Xã Long Hiệp',
      ),
      63 =>
      array(
        'maphuongxa' => 80908032,
        'tenphuongxa' => 'Xã Mỹ Thuận',
      ),
      64 =>
      array(
        'maphuongxa' => 81713068,
        'tenphuongxa' => 'Xã Tập Sơn',
      ),
      65 =>
      array(
        'maphuongxa' => 80907033,
        'tenphuongxa' => 'Phường Bình Minh',
      ),
      66 =>
      array(
        'maphuongxa' => 81716069,
        'tenphuongxa' => 'Phường Duyên Hải',
      ),
      67 =>
      array(
        'maphuongxa' => 80907034,
        'tenphuongxa' => 'Phường Cái Vồn',
      ),
      68 =>
      array(
        'maphuongxa' => 81716070,
        'tenphuongxa' => 'Phường Trường Long Hòa',
      ),
      69 =>
      array(
        'maphuongxa' => 80907035,
        'tenphuongxa' => 'Phường Đông Thành',
      ),
      70 =>
      array(
        'maphuongxa' => 81716071,
        'tenphuongxa' => 'Xã Long Hữu',
      ),
      71 =>
      array(
        'maphuongxa' => 81715072,
        'tenphuongxa' => 'Xã Long Thành',
      ),
      72 =>
      array(
        'maphuongxa' => 81715073,
        'tenphuongxa' => 'Xã Đông Hải',
      ),
      73 =>
      array(
        'maphuongxa' => 81715074,
        'tenphuongxa' => 'Xã Long Vĩnh',
      ),
      74 =>
      array(
        'maphuongxa' => 81715075,
        'tenphuongxa' => 'Xã Đôn Châu',
      ),
      75 =>
      array(
        'maphuongxa' => 81715076,
        'tenphuongxa' => 'Xã Ngũ Lạc',
      ),
      76 =>
      array(
        'maphuongxa' => 81101077,
        'tenphuongxa' => 'Phường An Hội',
      ),
      77 =>
      array(
        'maphuongxa' => 81101078,
        'tenphuongxa' => 'Phường Phú Khương',
      ),
      78 =>
      array(
        'maphuongxa' => 81101079,
        'tenphuongxa' => 'Phường Bến Tre',
      ),
      79 =>
      array(
        'maphuongxa' => 81101080,
        'tenphuongxa' => 'Phường Sơn Đông',
      ),
      80 =>
      array(
        'maphuongxa' => 81103081,
        'tenphuongxa' => 'Phường Phú Tân',
      ),
      81 =>
      array(
        'maphuongxa' => 81103082,
        'tenphuongxa' => 'Xã Phú Túc',
      ),
      82 =>
      array(
        'maphuongxa' => 81103083,
        'tenphuongxa' => 'Xã Giao Long',
      ),
      83 =>
      array(
        'maphuongxa' => 81103084,
        'tenphuongxa' => 'Xã Tiên Thủy',
      ),
      84 =>
      array(
        'maphuongxa' => 81103085,
        'tenphuongxa' => 'Xã Tân Phú',
      ),
      85 =>
      array(
        'maphuongxa' => 81105086,
        'tenphuongxa' => 'Xã Phú Phụng',
      ),
      86 =>
      array(
        'maphuongxa' => 81105087,
        'tenphuongxa' => 'Xã Chợ Lách',
      ),
      87 =>
      array(
        'maphuongxa' => 81105088,
        'tenphuongxa' => 'Xã Vĩnh Thành',
      ),
      88 =>
      array(
        'maphuongxa' => 81105089,
        'tenphuongxa' => 'Xã Hưng Khánh Trung',
      ),
      89 =>
      array(
        'maphuongxa' => 81108090,
        'tenphuongxa' => 'Xã Phước Mỹ Trung',
      ),
      90 =>
      array(
        'maphuongxa' => 81108091,
        'tenphuongxa' => 'Xã Tân Thành Bình',
      ),
      91 =>
      array(
        'maphuongxa' => 81108092,
        'tenphuongxa' => 'Xã Nhuận Phú Tân',
      ),
      92 =>
      array(
        'maphuongxa' => 81107093,
        'tenphuongxa' => 'Xã Đồng Khởi',
      ),
      93 =>
      array(
        'maphuongxa' => 81107094,
        'tenphuongxa' => 'Xã Mỏ Cày',
      ),
      94 =>
      array(
        'maphuongxa' => 81107095,
        'tenphuongxa' => 'Xã Thành Thới',
      ),
      95 =>
      array(
        'maphuongxa' => 81107096,
        'tenphuongxa' => 'Xã An Định',
      ),
      96 =>
      array(
        'maphuongxa' => 81107097,
        'tenphuongxa' => 'Xã Hương Mỹ',
      ),
      97 =>
      array(
        'maphuongxa' => 81115098,
        'tenphuongxa' => 'Xã Đại Điền',
      ),
      98 =>
      array(
        'maphuongxa' => 81115099,
        'tenphuongxa' => 'Xã Quới Điền',
      ),
      99 =>
      array(
        'maphuongxa' => 81115100,
        'tenphuongxa' => 'Xã Thạnh Phú',
      ),
      100 =>
      array(
        'maphuongxa' => 81115101,
        'tenphuongxa' => 'Xã An Qui',
      ),
      101 =>
      array(
        'maphuongxa' => 81115102,
        'tenphuongxa' => 'Xã Thạnh Hải',
      ),
      102 =>
      array(
        'maphuongxa' => 81115103,
        'tenphuongxa' => 'Xã Thạnh Phong',
      ),
      103 =>
      array(
        'maphuongxa' => 81113104,
        'tenphuongxa' => 'Xã Tân Thủy',
      ),
      104 =>
      array(
        'maphuongxa' => 81113105,
        'tenphuongxa' => 'Xã Bảo Thạnh',
      ),
      105 =>
      array(
        'maphuongxa' => 81113106,
        'tenphuongxa' => 'Xã Ba Tri',
      ),
      106 =>
      array(
        'maphuongxa' => 81113107,
        'tenphuongxa' => 'Xã Tân Xuân',
      ),
      107 =>
      array(
        'maphuongxa' => 81113108,
        'tenphuongxa' => 'Xã Mỹ Chánh Hòa',
      ),
      108 =>
      array(
        'maphuongxa' => 81113109,
        'tenphuongxa' => 'Xã An Ngãi Trung',
      ),
      109 =>
      array(
        'maphuongxa' => 81113110,
        'tenphuongxa' => 'Xã An Hiệp',
      ),
      110 =>
      array(
        'maphuongxa' => 81109111,
        'tenphuongxa' => 'Xã Hưng Nhượng',
      ),
      111 =>
      array(
        'maphuongxa' => 81109112,
        'tenphuongxa' => 'Xã Giồng Trôm',
      ),
      112 =>
      array(
        'maphuongxa' => 81109113,
        'tenphuongxa' => 'Xã Tân Hào',
      ),
      113 =>
      array(
        'maphuongxa' => 81109114,
        'tenphuongxa' => 'Xã Phước Long',
      ),
      114 =>
      array(
        'maphuongxa' => 81109115,
        'tenphuongxa' => 'Xã Lương Phú',
      ),
      115 =>
      array(
        'maphuongxa' => 81109116,
        'tenphuongxa' => 'Xã Châu Hòa',
      ),
      116 =>
      array(
        'maphuongxa' => 81109117,
        'tenphuongxa' => 'Xã Lương Hòa',
      ),
      117 =>
      array(
        'maphuongxa' => 81111118,
        'tenphuongxa' => 'Xã Thới Thuận',
      ),
      118 =>
      array(
        'maphuongxa' => 81111119,
        'tenphuongxa' => 'Xã Thạnh Phước',
      ),
      119 =>
      array(
        'maphuongxa' => 81111120,
        'tenphuongxa' => 'Xã Bình Đại',
      ),
      120 =>
      array(
        'maphuongxa' => 81111121,
        'tenphuongxa' => 'Xã Thạnh Trị',
      ),
      121 =>
      array(
        'maphuongxa' => 81111122,
        'tenphuongxa' => 'Xã Lộc Thuận',
      ),
      122 =>
      array(
        'maphuongxa' => 81111123,
        'tenphuongxa' => 'Xã Châu Hưng',
      ),
      123 =>
      array(
        'maphuongxa' => 81111124,
        'tenphuongxa' => 'Xã Phú Thuận',
      ),
    ),
  ),
  30 =>
  array(
    'matinhBNV' => 31,
    'matinhTMS' => '803',
    'tentinhmoi' => 'Tỉnh Đồng Tháp',
    'phuongxa' =>
    array(
      0 =>
      array(
        'maphuongxa' => 80701001,
        'tenphuongxa' => 'Phường Mỹ Tho',
      ),
      1 =>
      array(
        'maphuongxa' => 80701002,
        'tenphuongxa' => 'Phường Đạo Thạnh',
      ),
      2 =>
      array(
        'maphuongxa' => 80701003,
        'tenphuongxa' => 'Phường Mỹ Phong',
      ),
      3 =>
      array(
        'maphuongxa' => 80701004,
        'tenphuongxa' => 'Phường Thới Sơn',
      ),
      4 =>
      array(
        'maphuongxa' => 80701005,
        'tenphuongxa' => 'Phường Trung An',
      ),
      5 =>
      array(
        'maphuongxa' => 80703006,
        'tenphuongxa' => 'Phường Gò Công',
      ),
      6 =>
      array(
        'maphuongxa' => 80703007,
        'tenphuongxa' => 'Phường Long Thuận',
      ),
      7 =>
      array(
        'maphuongxa' => 80703008,
        'tenphuongxa' => 'Phường Sơn Qui',
      ),
      8 =>
      array(
        'maphuongxa' => 80703009,
        'tenphuongxa' => 'Phường Bình Xuân',
      ),
      9 =>
      array(
        'maphuongxa' => 80721010,
        'tenphuongxa' => 'Phường Mỹ Phước Tây',
      ),
      10 =>
      array(
        'maphuongxa' => 80721011,
        'tenphuongxa' => 'Phường Thanh Hoà',
      ),
      11 =>
      array(
        'maphuongxa' => 80721012,
        'tenphuongxa' => 'Phường Cai Lậy',
      ),
      12 =>
      array(
        'maphuongxa' => 80721013,
        'tenphuongxa' => 'Phường Nhị Quý',
      ),
      13 =>
      array(
        'maphuongxa' => 80721014,
        'tenphuongxa' => 'Xã Tân Phú',
      ),
      14 =>
      array(
        'maphuongxa' => 80713015,
        'tenphuongxa' => 'Xã Thanh Hưng',
      ),
      15 =>
      array(
        'maphuongxa' => 80713016,
        'tenphuongxa' => 'Xã An Hữu',
      ),
      16 =>
      array(
        'maphuongxa' => 80713017,
        'tenphuongxa' => 'Xã Mỹ Lợi',
      ),
      17 =>
      array(
        'maphuongxa' => 80713018,
        'tenphuongxa' => 'Xã Mỹ Đức Tây',
      ),
      18 =>
      array(
        'maphuongxa' => 80713019,
        'tenphuongxa' => 'Xã Mỹ Thiện',
      ),
      19 =>
      array(
        'maphuongxa' => 80713020,
        'tenphuongxa' => 'Xã Hậu Mỹ',
      ),
      20 =>
      array(
        'maphuongxa' => 80713021,
        'tenphuongxa' => 'Xã Hội Cư',
      ),
      21 =>
      array(
        'maphuongxa' => 80713022,
        'tenphuongxa' => 'Xã Cái Bè',
      ),
      22 =>
      array(
        'maphuongxa' => 80709023,
        'tenphuongxa' => 'Xã Bình Phú',
      ),
      23 =>
      array(
        'maphuongxa' => 80709024,
        'tenphuongxa' => 'Xã Hiệp Đức',
      ),
      24 =>
      array(
        'maphuongxa' => 80709025,
        'tenphuongxa' => 'Xã Ngũ Hiệp',
      ),
      25 =>
      array(
        'maphuongxa' => 80709026,
        'tenphuongxa' => 'Xã Long Tiên',
      ),
      26 =>
      array(
        'maphuongxa' => 80709027,
        'tenphuongxa' => 'Xã Mỹ Thành',
      ),
      27 =>
      array(
        'maphuongxa' => 80709028,
        'tenphuongxa' => 'Xã Thạnh Phú',
      ),
      28 =>
      array(
        'maphuongxa' => 80705029,
        'tenphuongxa' => 'Xã Tân Phước 1',
      ),
      29 =>
      array(
        'maphuongxa' => 80705030,
        'tenphuongxa' => 'Xã Tân Phước 2',
      ),
      30 =>
      array(
        'maphuongxa' => 80705031,
        'tenphuongxa' => 'Xã Tân Phước 3',
      ),
      31 =>
      array(
        'maphuongxa' => 80705032,
        'tenphuongxa' => 'Xã Hưng Thạnh',
      ),
      32 =>
      array(
        'maphuongxa' => 80707033,
        'tenphuongxa' => 'Xã Tân Hương',
      ),
      33 =>
      array(
        'maphuongxa' => 80707034,
        'tenphuongxa' => 'Xã Châu Thành',
      ),
      34 =>
      array(
        'maphuongxa' => 80707035,
        'tenphuongxa' => 'Xã Long Hưng',
      ),
      35 =>
      array(
        'maphuongxa' => 80707036,
        'tenphuongxa' => 'Xã Long Định',
      ),
      36 =>
      array(
        'maphuongxa' => 80707037,
        'tenphuongxa' => 'Xã Vĩnh Kim',
      ),
      37 =>
      array(
        'maphuongxa' => 80707038,
        'tenphuongxa' => 'Xã Kim Sơn',
      ),
      38 =>
      array(
        'maphuongxa' => 80707039,
        'tenphuongxa' => 'Xã Bình Trưng',
      ),
      39 =>
      array(
        'maphuongxa' => 80711040,
        'tenphuongxa' => 'Xã Mỹ Tịnh An',
      ),
      40 =>
      array(
        'maphuongxa' => 80711041,
        'tenphuongxa' => 'Xã Lương Hoà Lạc',
      ),
      41 =>
      array(
        'maphuongxa' => 80711042,
        'tenphuongxa' => 'Xã Tân Thuận Bình',
      ),
      42 =>
      array(
        'maphuongxa' => 80711043,
        'tenphuongxa' => 'Xã Chợ Gạo',
      ),
      43 =>
      array(
        'maphuongxa' => 80711044,
        'tenphuongxa' => 'Xã An Thạnh Thủy',
      ),
      44 =>
      array(
        'maphuongxa' => 80711045,
        'tenphuongxa' => 'Xã Bình Ninh',
      ),
      45 =>
      array(
        'maphuongxa' => 80715046,
        'tenphuongxa' => 'Xã Vĩnh Bình',
      ),
      46 =>
      array(
        'maphuongxa' => 80715047,
        'tenphuongxa' => 'Xã Đồng Sơn',
      ),
      47 =>
      array(
        'maphuongxa' => 80715048,
        'tenphuongxa' => 'Xã Phú Thành',
      ),
      48 =>
      array(
        'maphuongxa' => 80715049,
        'tenphuongxa' => 'Xã Long Bình',
      ),
      49 =>
      array(
        'maphuongxa' => 80715050,
        'tenphuongxa' => 'Xã Vĩnh Hựu',
      ),
      50 =>
      array(
        'maphuongxa' => 80717051,
        'tenphuongxa' => 'Xã Gò Công Đông',
      ),
      51 =>
      array(
        'maphuongxa' => 80717052,
        'tenphuongxa' => 'Xã Tân Điền',
      ),
      52 =>
      array(
        'maphuongxa' => 80717053,
        'tenphuongxa' => 'Xã Tân Hoà',
      ),
      53 =>
      array(
        'maphuongxa' => 80717054,
        'tenphuongxa' => 'Xã Tân Đông',
      ),
      54 =>
      array(
        'maphuongxa' => 80717055,
        'tenphuongxa' => 'Xã Gia Thuận',
      ),
      55 =>
      array(
        'maphuongxa' => 80719056,
        'tenphuongxa' => 'Xã Tân Thới',
      ),
      56 =>
      array(
        'maphuongxa' => 80719057,
        'tenphuongxa' => 'Xã Tân Phú Đông',
      ),
      57 =>
      array(
        'maphuongxa' => 80305058,
        'tenphuongxa' => 'Xã Tân Hồng',
      ),
      58 =>
      array(
        'maphuongxa' => 80305059,
        'tenphuongxa' => 'Xã Tân Thành',
      ),
      59 =>
      array(
        'maphuongxa' => 80305060,
        'tenphuongxa' => 'Xã Tân Hộ Cơ',
      ),
      60 =>
      array(
        'maphuongxa' => 80305061,
        'tenphuongxa' => 'Xã An Phước',
      ),
      61 =>
      array(
        'maphuongxa' => 80323062,
        'tenphuongxa' => 'Phường An Bình',
      ),
      62 =>
      array(
        'maphuongxa' => 80323063,
        'tenphuongxa' => 'Phường Hồng Ngự',
      ),
      63 =>
      array(
        'maphuongxa' => 80307064,
        'tenphuongxa' => 'Phường Thường Lạc',
      ),
      64 =>
      array(
        'maphuongxa' => 80307065,
        'tenphuongxa' => 'Xã Thường Phước',
      ),
      65 =>
      array(
        'maphuongxa' => 80307066,
        'tenphuongxa' => 'Xã Long Khánh',
      ),
      66 =>
      array(
        'maphuongxa' => 80307067,
        'tenphuongxa' => 'Xã Long Phú Thuận',
      ),
      67 =>
      array(
        'maphuongxa' => 80309068,
        'tenphuongxa' => 'Xã An Hoà',
      ),
      68 =>
      array(
        'maphuongxa' => 80309069,
        'tenphuongxa' => 'Xã Tam Nông',
      ),
      69 =>
      array(
        'maphuongxa' => 80309070,
        'tenphuongxa' => 'Xã Phú Thọ',
      ),
      70 =>
      array(
        'maphuongxa' => 80309071,
        'tenphuongxa' => 'Xã Tràm Chim',
      ),
      71 =>
      array(
        'maphuongxa' => 80309072,
        'tenphuongxa' => 'Xã Phú Cường',
      ),
      72 =>
      array(
        'maphuongxa' => 80309073,
        'tenphuongxa' => 'Xã An Long',
      ),
      73 =>
      array(
        'maphuongxa' => 80311074,
        'tenphuongxa' => 'Xã Thanh Bình',
      ),
      74 =>
      array(
        'maphuongxa' => 80311075,
        'tenphuongxa' => 'Xã Tân Thạnh',
      ),
      75 =>
      array(
        'maphuongxa' => 80311076,
        'tenphuongxa' => 'Xã Bình Thành',
      ),
      76 =>
      array(
        'maphuongxa' => 80311077,
        'tenphuongxa' => 'Xã Tân Long',
      ),
      77 =>
      array(
        'maphuongxa' => 80313078,
        'tenphuongxa' => 'Xã Tháp Mười',
      ),
      78 =>
      array(
        'maphuongxa' => 80313079,
        'tenphuongxa' => 'Xã Thanh Mỹ',
      ),
      79 =>
      array(
        'maphuongxa' => 80313080,
        'tenphuongxa' => 'Xã Mỹ Quí',
      ),
      80 =>
      array(
        'maphuongxa' => 80313081,
        'tenphuongxa' => 'Xã Đốc Binh Kiều',
      ),
      81 =>
      array(
        'maphuongxa' => 80313082,
        'tenphuongxa' => 'Xã Trường Xuân',
      ),
      82 =>
      array(
        'maphuongxa' => 80313083,
        'tenphuongxa' => 'Xã Phương Thịnh',
      ),
      83 =>
      array(
        'maphuongxa' => 80315084,
        'tenphuongxa' => 'Xã Phong Mỹ',
      ),
      84 =>
      array(
        'maphuongxa' => 80315085,
        'tenphuongxa' => 'Xã Ba Sao',
      ),
      85 =>
      array(
        'maphuongxa' => 80315086,
        'tenphuongxa' => 'Xã Mỹ Thọ',
      ),
      86 =>
      array(
        'maphuongxa' => 80315087,
        'tenphuongxa' => 'Xã Bình Hàng Trung',
      ),
      87 =>
      array(
        'maphuongxa' => 80315088,
        'tenphuongxa' => 'Xã Mỹ Hiệp',
      ),
      88 =>
      array(
        'maphuongxa' => 80301089,
        'tenphuongxa' => 'Phường Cao Lãnh',
      ),
      89 =>
      array(
        'maphuongxa' => 80301090,
        'tenphuongxa' => 'Phường Mỹ Ngãi',
      ),
      90 =>
      array(
        'maphuongxa' => 80301091,
        'tenphuongxa' => 'Phường Mỹ Trà',
      ),
      91 =>
      array(
        'maphuongxa' => 80317092,
        'tenphuongxa' => 'Xã Mỹ An Hưng',
      ),
      92 =>
      array(
        'maphuongxa' => 80317093,
        'tenphuongxa' => 'Xã Tân Khánh Trung',
      ),
      93 =>
      array(
        'maphuongxa' => 80317094,
        'tenphuongxa' => 'Xã Lấp Vò',
      ),
      94 =>
      array(
        'maphuongxa' => 80319095,
        'tenphuongxa' => 'Xã Lai Vung',
      ),
      95 =>
      array(
        'maphuongxa' => 80319096,
        'tenphuongxa' => 'Xã Hoà Long',
      ),
      96 =>
      array(
        'maphuongxa' => 80319097,
        'tenphuongxa' => 'Xã Phong Hoà',
      ),
      97 =>
      array(
        'maphuongxa' => 80303098,
        'tenphuongxa' => 'Phường Sa Đéc',
      ),
      98 =>
      array(
        'maphuongxa' => 80319099,
        'tenphuongxa' => 'Xã Tân Dương',
      ),
      99 =>
      array(
        'maphuongxa' => 80321100,
        'tenphuongxa' => 'Xã Phú Hựu',
      ),
      100 =>
      array(
        'maphuongxa' => 80321101,
        'tenphuongxa' => 'Xã Tân Nhuận Đông',
      ),
      101 =>
      array(
        'maphuongxa' => 80321102,
        'tenphuongxa' => 'Xã Tân Phú Trung',
      ),
    ),
  ),
  31 =>
  array(
    'matinhBNV' => 32,
    'matinhTMS' => '805',
    'tentinhmoi' => 'Tỉnh An Giang',
    'phuongxa' =>
    array(
      0 =>
      array(
        'maphuongxa' => 80501001,
        'tenphuongxa' => 'Xã Mỹ Hoà Hưng',
      ),
      1 =>
      array(
        'maphuongxa' => 80501002,
        'tenphuongxa' => 'Phường Long Xuyên',
      ),
      2 =>
      array(
        'maphuongxa' => 80501003,
        'tenphuongxa' => 'Phường Bình Đức',
      ),
      3 =>
      array(
        'maphuongxa' => 80501004,
        'tenphuongxa' => 'Phường Mỹ Thới',
      ),
      4 =>
      array(
        'maphuongxa' => 80503005,
        'tenphuongxa' => 'Phường Châu Đốc',
      ),
      5 =>
      array(
        'maphuongxa' => 80503006,
        'tenphuongxa' => 'Phường Vĩnh Tế',
      ),
      6 =>
      array(
        'maphuongxa' => 80505007,
        'tenphuongxa' => 'Xã An Phú',
      ),
      7 =>
      array(
        'maphuongxa' => 80505008,
        'tenphuongxa' => 'Xã Vĩnh Hậu',
      ),
      8 =>
      array(
        'maphuongxa' => 80505009,
        'tenphuongxa' => 'Xã Nhơn Hội',
      ),
      9 =>
      array(
        'maphuongxa' => 80505010,
        'tenphuongxa' => 'Xã Khánh Bình',
      ),
      10 =>
      array(
        'maphuongxa' => 80505011,
        'tenphuongxa' => 'Xã Phú Hữu',
      ),
      11 =>
      array(
        'maphuongxa' => 80507012,
        'tenphuongxa' => 'Xã Tân An',
      ),
      12 =>
      array(
        'maphuongxa' => 80507013,
        'tenphuongxa' => 'Xã Châu Phong',
      ),
      13 =>
      array(
        'maphuongxa' => 80507014,
        'tenphuongxa' => 'Xã Vĩnh Xương',
      ),
      14 =>
      array(
        'maphuongxa' => 80507015,
        'tenphuongxa' => 'Phường Tân Châu',
      ),
      15 =>
      array(
        'maphuongxa' => 80507016,
        'tenphuongxa' => 'Phường Long Phú',
      ),
      16 =>
      array(
        'maphuongxa' => 80509017,
        'tenphuongxa' => 'Xã Phú Tân',
      ),
      17 =>
      array(
        'maphuongxa' => 80509018,
        'tenphuongxa' => 'Xã Phú An',
      ),
      18 =>
      array(
        'maphuongxa' => 80509019,
        'tenphuongxa' => 'Xã Bình Thạnh Đông',
      ),
      19 =>
      array(
        'maphuongxa' => 80509020,
        'tenphuongxa' => 'Xã Chợ Vàm',
      ),
      20 =>
      array(
        'maphuongxa' => 80509021,
        'tenphuongxa' => 'Xã Hoà Lạc',
      ),
      21 =>
      array(
        'maphuongxa' => 80509022,
        'tenphuongxa' => 'Xã Phú Lâm',
      ),
      22 =>
      array(
        'maphuongxa' => 80511023,
        'tenphuongxa' => 'Xã Châu Phú',
      ),
      23 =>
      array(
        'maphuongxa' => 80511024,
        'tenphuongxa' => 'Xã Mỹ Đức',
      ),
      24 =>
      array(
        'maphuongxa' => 80511025,
        'tenphuongxa' => 'Xã Vĩnh Thạnh Trung',
      ),
      25 =>
      array(
        'maphuongxa' => 80511026,
        'tenphuongxa' => 'Xã Bình Mỹ',
      ),
      26 =>
      array(
        'maphuongxa' => 80511027,
        'tenphuongxa' => 'Xã Thạnh Mỹ Tây',
      ),
      27 =>
      array(
        'maphuongxa' => 80513028,
        'tenphuongxa' => 'Xã An Cư',
      ),
      28 =>
      array(
        'maphuongxa' => 80513029,
        'tenphuongxa' => 'Xã Núi Cấm',
      ),
      29 =>
      array(
        'maphuongxa' => 80513030,
        'tenphuongxa' => 'Phường Tịnh Biên',
      ),
      30 =>
      array(
        'maphuongxa' => 80513031,
        'tenphuongxa' => 'Phường Thới Sơn',
      ),
      31 =>
      array(
        'maphuongxa' => 80513032,
        'tenphuongxa' => 'Phường Chi Lăng',
      ),
      32 =>
      array(
        'maphuongxa' => 80515033,
        'tenphuongxa' => 'Xã Ba Chúc',
      ),
      33 =>
      array(
        'maphuongxa' => 80515034,
        'tenphuongxa' => 'Xã Tri Tôn',
      ),
      34 =>
      array(
        'maphuongxa' => 80515035,
        'tenphuongxa' => 'Xã Ô Lâm',
      ),
      35 =>
      array(
        'maphuongxa' => 80515036,
        'tenphuongxa' => 'Xã Cô Tô',
      ),
      36 =>
      array(
        'maphuongxa' => 80515037,
        'tenphuongxa' => 'Xã Vĩnh Gia',
      ),
      37 =>
      array(
        'maphuongxa' => 80519038,
        'tenphuongxa' => 'Xã An Châu',
      ),
      38 =>
      array(
        'maphuongxa' => 80519039,
        'tenphuongxa' => 'Xã Bình Hoà',
      ),
      39 =>
      array(
        'maphuongxa' => 80519040,
        'tenphuongxa' => 'Xã Cần Đăng',
      ),
      40 =>
      array(
        'maphuongxa' => 80519041,
        'tenphuongxa' => 'Xã Vĩnh Hanh',
      ),
      41 =>
      array(
        'maphuongxa' => 80519042,
        'tenphuongxa' => 'Xã Vĩnh An',
      ),
      42 =>
      array(
        'maphuongxa' => 80517043,
        'tenphuongxa' => 'Xã Chợ Mới',
      ),
      43 =>
      array(
        'maphuongxa' => 80517044,
        'tenphuongxa' => 'Xã Cù Lao Giêng',
      ),
      44 =>
      array(
        'maphuongxa' => 80517045,
        'tenphuongxa' => 'Xã Hội An',
      ),
      45 =>
      array(
        'maphuongxa' => 80517046,
        'tenphuongxa' => 'Xã Long Điền',
      ),
      46 =>
      array(
        'maphuongxa' => 80517047,
        'tenphuongxa' => 'Xã Nhơn Mỹ',
      ),
      47 =>
      array(
        'maphuongxa' => 80517048,
        'tenphuongxa' => 'Xã Long Kiến',
      ),
      48 =>
      array(
        'maphuongxa' => 80521049,
        'tenphuongxa' => 'Xã Thoại Sơn',
      ),
      49 =>
      array(
        'maphuongxa' => 80521050,
        'tenphuongxa' => 'Xã Óc Eo',
      ),
      50 =>
      array(
        'maphuongxa' => 80521051,
        'tenphuongxa' => 'Xã Định Mỹ',
      ),
      51 =>
      array(
        'maphuongxa' => 80521052,
        'tenphuongxa' => 'Xã Phú Hoà',
      ),
      52 =>
      array(
        'maphuongxa' => 80521053,
        'tenphuongxa' => 'Xã Vĩnh Trạch',
      ),
      53 =>
      array(
        'maphuongxa' => 80521054,
        'tenphuongxa' => 'Xã Tây Phú',
      ),
      54 =>
      array(
        'maphuongxa' => 81319055,
        'tenphuongxa' => 'Xã Vĩnh Bình',
      ),
      55 =>
      array(
        'maphuongxa' => 81319056,
        'tenphuongxa' => 'Xã Vĩnh Thuận',
      ),
      56 =>
      array(
        'maphuongxa' => 81319057,
        'tenphuongxa' => 'Xã Vĩnh Phong',
      ),
      57 =>
      array(
        'maphuongxa' => 81327058,
        'tenphuongxa' => 'Xã Vĩnh Hoà',
      ),
      58 =>
      array(
        'maphuongxa' => 81327059,
        'tenphuongxa' => 'Xã U Minh Thượng',
      ),
      59 =>
      array(
        'maphuongxa' => 81317060,
        'tenphuongxa' => 'Xã Đông Hoà',
      ),
      60 =>
      array(
        'maphuongxa' => 81317061,
        'tenphuongxa' => 'Xã Tân Thạnh',
      ),
      61 =>
      array(
        'maphuongxa' => 81317062,
        'tenphuongxa' => 'Xã Đông Hưng',
      ),
      62 =>
      array(
        'maphuongxa' => 81317063,
        'tenphuongxa' => 'Xã An Minh',
      ),
      63 =>
      array(
        'maphuongxa' => 81317064,
        'tenphuongxa' => 'Xã Vân Khánh',
      ),
      64 =>
      array(
        'maphuongxa' => 81315065,
        'tenphuongxa' => 'Xã Tây Yên',
      ),
      65 =>
      array(
        'maphuongxa' => 81315066,
        'tenphuongxa' => 'Xã Đông Thái',
      ),
      66 =>
      array(
        'maphuongxa' => 81315067,
        'tenphuongxa' => 'Xã An Biên',
      ),
      67 =>
      array(
        'maphuongxa' => 81313068,
        'tenphuongxa' => 'Xã Định Hoà',
      ),
      68 =>
      array(
        'maphuongxa' => 81313069,
        'tenphuongxa' => 'Xã Gò Quao',
      ),
      69 =>
      array(
        'maphuongxa' => 81313070,
        'tenphuongxa' => 'Xã Vĩnh Hoà Hưng',
      ),
      70 =>
      array(
        'maphuongxa' => 81313071,
        'tenphuongxa' => 'Xã Vĩnh Tuy',
      ),
      71 =>
      array(
        'maphuongxa' => 81311072,
        'tenphuongxa' => 'Xã Giồng Riềng',
      ),
      72 =>
      array(
        'maphuongxa' => 81311073,
        'tenphuongxa' => 'Xã Thạnh Hưng',
      ),
      73 =>
      array(
        'maphuongxa' => 81311074,
        'tenphuongxa' => 'Xã Long Thạnh',
      ),
      74 =>
      array(
        'maphuongxa' => 81311075,
        'tenphuongxa' => 'Xã Hoà Hưng',
      ),
      75 =>
      array(
        'maphuongxa' => 81311076,
        'tenphuongxa' => 'Xã Ngọc Chúc',
      ),
      76 =>
      array(
        'maphuongxa' => 81311077,
        'tenphuongxa' => 'Xã Hoà Thuận',
      ),
      77 =>
      array(
        'maphuongxa' => 81307078,
        'tenphuongxa' => 'Xã Tân Hội',
      ),
      78 =>
      array(
        'maphuongxa' => 81307079,
        'tenphuongxa' => 'Xã Tân Hiệp',
      ),
      79 =>
      array(
        'maphuongxa' => 81307080,
        'tenphuongxa' => 'Xã Thạnh Đông',
      ),
      80 =>
      array(
        'maphuongxa' => 81309081,
        'tenphuongxa' => 'Xã Thạnh Lộc',
      ),
      81 =>
      array(
        'maphuongxa' => 81309082,
        'tenphuongxa' => 'Xã Châu Thành',
      ),
      82 =>
      array(
        'maphuongxa' => 81309083,
        'tenphuongxa' => 'Xã Bình An',
      ),
      83 =>
      array(
        'maphuongxa' => 81305084,
        'tenphuongxa' => 'Xã Hòn Đất',
      ),
      84 =>
      array(
        'maphuongxa' => 81305085,
        'tenphuongxa' => 'Xã Sơn Kiên',
      ),
      85 =>
      array(
        'maphuongxa' => 81305086,
        'tenphuongxa' => 'Xã Mỹ Thuận',
      ),
      86 =>
      array(
        'maphuongxa' => 81305087,
        'tenphuongxa' => 'Xã Bình Sơn',
      ),
      87 =>
      array(
        'maphuongxa' => 81305088,
        'tenphuongxa' => 'Xã Bình Giang',
      ),
      88 =>
      array(
        'maphuongxa' => 81304089,
        'tenphuongxa' => 'Xã Giang Thành',
      ),
      89 =>
      array(
        'maphuongxa' => 81304090,
        'tenphuongxa' => 'Xã Vĩnh Điều',
      ),
      90 =>
      array(
        'maphuongxa' => 81303091,
        'tenphuongxa' => 'Xã Hoà Điền',
      ),
      91 =>
      array(
        'maphuongxa' => 81303092,
        'tenphuongxa' => 'Xã Kiên Lương',
      ),
      92 =>
      array(
        'maphuongxa' => 81303093,
        'tenphuongxa' => 'Xã Sơn Hải',
      ),
      93 =>
      array(
        'maphuongxa' => 81303094,
        'tenphuongxa' => 'Xã Hòn Nghệ',
      ),
      94 =>
      array(
        'maphuongxa' => 81323095,
        'tenphuongxa' => 'Đặc khu Kiên Hải',
      ),
      95 =>
      array(
        'maphuongxa' => 81301096,
        'tenphuongxa' => 'Phường Vĩnh Thông',
      ),
      96 =>
      array(
        'maphuongxa' => 81301097,
        'tenphuongxa' => 'Phường Rạch Giá',
      ),
      97 =>
      array(
        'maphuongxa' => 81325098,
        'tenphuongxa' => 'Phường Hà Tiên',
      ),
      98 =>
      array(
        'maphuongxa' => 81325099,
        'tenphuongxa' => 'Phường Tô Châu',
      ),
      99 =>
      array(
        'maphuongxa' => 81325100,
        'tenphuongxa' => 'Xã Tiên Hải',
      ),
      100 =>
      array(
        'maphuongxa' => 81321101,
        'tenphuongxa' => 'Đặc khu Phú Quốc',
      ),
      101 =>
      array(
        'maphuongxa' => 81321102,
        'tenphuongxa' => 'Đặc khu Thổ Châu',
      ),
    ),
  ),
  32 =>
  array(
    'matinhBNV' => 33,
    'matinhTMS' => '815',
    'tentinhmoi' => 'Thành Phố Cần Thơ',
    'phuongxa' =>
    array(
      0 =>
      array(
        'maphuongxa' => 81519001,
        'tenphuongxa' => 'Phường Ninh Kiều',
      ),
      1 =>
      array(
        'maphuongxa' => 81519002,
        'tenphuongxa' => 'Phường Cái Khế',
      ),
      2 =>
      array(
        'maphuongxa' => 81519003,
        'tenphuongxa' => 'Phường Tân An',
      ),
      3 =>
      array(
        'maphuongxa' => 81519004,
        'tenphuongxa' => 'Phường An Bình',
      ),
      4 =>
      array(
        'maphuongxa' => 81521005,
        'tenphuongxa' => 'Phường Thới An Đông',
      ),
      5 =>
      array(
        'maphuongxa' => 81521006,
        'tenphuongxa' => 'Phường Bình Thủy',
      ),
      6 =>
      array(
        'maphuongxa' => 81521007,
        'tenphuongxa' => 'Phường Long Tuyền',
      ),
      7 =>
      array(
        'maphuongxa' => 81523008,
        'tenphuongxa' => 'Phường Cái Răng',
      ),
      8 =>
      array(
        'maphuongxa' => 81523009,
        'tenphuongxa' => 'Phường Hưng Phú',
      ),
      9 =>
      array(
        'maphuongxa' => 81505010,
        'tenphuongxa' => 'Phường Ô Môn',
      ),
      10 =>
      array(
        'maphuongxa' => 81505011,
        'tenphuongxa' => 'Phường Thới Long',
      ),
      11 =>
      array(
        'maphuongxa' => 81505012,
        'tenphuongxa' => 'Phường Phước Thới',
      ),
      12 =>
      array(
        'maphuongxa' => 81503013,
        'tenphuongxa' => 'Phường Trung Nhứt',
      ),
      13 =>
      array(
        'maphuongxa' => 81503014,
        'tenphuongxa' => 'Phường Thốt Nốt',
      ),
      14 =>
      array(
        'maphuongxa' => 81503015,
        'tenphuongxa' => 'Phường Thuận Hưng',
      ),
      15 =>
      array(
        'maphuongxa' => 81503016,
        'tenphuongxa' => 'Phường Tân Lộc',
      ),
      16 =>
      array(
        'maphuongxa' => 81529017,
        'tenphuongxa' => 'Xã Phong Điền',
      ),
      17 =>
      array(
        'maphuongxa' => 81529018,
        'tenphuongxa' => 'Xã Nhơn Ái',
      ),
      18 =>
      array(
        'maphuongxa' => 81529019,
        'tenphuongxa' => 'Xã Trường Long',
      ),
      19 =>
      array(
        'maphuongxa' => 81531020,
        'tenphuongxa' => 'Xã Thới Lai',
      ),
      20 =>
      array(
        'maphuongxa' => 81531021,
        'tenphuongxa' => 'Xã Đông Thuận',
      ),
      21 =>
      array(
        'maphuongxa' => 81531022,
        'tenphuongxa' => 'Xã Trường Xuân',
      ),
      22 =>
      array(
        'maphuongxa' => 81531023,
        'tenphuongxa' => 'Xã Trường Thành',
      ),
      23 =>
      array(
        'maphuongxa' => 81527024,
        'tenphuongxa' => 'Xã Cờ Đỏ',
      ),
      24 =>
      array(
        'maphuongxa' => 81527025,
        'tenphuongxa' => 'Xã Đông Hiệp',
      ),
      25 =>
      array(
        'maphuongxa' => 81527026,
        'tenphuongxa' => 'Xã Thạnh Phú',
      ),
      26 =>
      array(
        'maphuongxa' => 81527027,
        'tenphuongxa' => 'Xã Thới Hưng',
      ),
      27 =>
      array(
        'maphuongxa' => 81527028,
        'tenphuongxa' => 'Xã Trung Hưng',
      ),
      28 =>
      array(
        'maphuongxa' => 81525029,
        'tenphuongxa' => 'Xã Vĩnh Thạnh',
      ),
      29 =>
      array(
        'maphuongxa' => 81525030,
        'tenphuongxa' => 'Xã Vĩnh Trinh',
      ),
      30 =>
      array(
        'maphuongxa' => 81525031,
        'tenphuongxa' => 'Xã Thạnh An',
      ),
      31 =>
      array(
        'maphuongxa' => 81525032,
        'tenphuongxa' => 'Xã Thạnh Quới',
      ),
      32 =>
      array(
        'maphuongxa' => 81601033,
        'tenphuongxa' => 'Xã Hỏa Lựu',
      ),
      33 =>
      array(
        'maphuongxa' => 81601034,
        'tenphuongxa' => 'Phường Vị Thanh',
      ),
      34 =>
      array(
        'maphuongxa' => 81601035,
        'tenphuongxa' => 'Phường Vị Tân',
      ),
      35 =>
      array(
        'maphuongxa' => 81609036,
        'tenphuongxa' => 'Xã Vị Thủy',
      ),
      36 =>
      array(
        'maphuongxa' => 81609037,
        'tenphuongxa' => 'Xã Vĩnh Thuận Đông',
      ),
      37 =>
      array(
        'maphuongxa' => 81609038,
        'tenphuongxa' => 'Xã Vị Thanh 1',
      ),
      38 =>
      array(
        'maphuongxa' => 81609039,
        'tenphuongxa' => 'Xã Vĩnh Tường',
      ),
      39 =>
      array(
        'maphuongxa' => 81611040,
        'tenphuongxa' => 'Xã Vĩnh Viễn',
      ),
      40 =>
      array(
        'maphuongxa' => 81611041,
        'tenphuongxa' => 'Xã Xà Phiên',
      ),
      41 =>
      array(
        'maphuongxa' => 81611042,
        'tenphuongxa' => 'Xã Lương Tâm',
      ),
      42 =>
      array(
        'maphuongxa' => 81612043,
        'tenphuongxa' => 'Phường Long Bình',
      ),
      43 =>
      array(
        'maphuongxa' => 81612044,
        'tenphuongxa' => 'Phường Long Mỹ',
      ),
      44 =>
      array(
        'maphuongxa' => 81612045,
        'tenphuongxa' => 'Phường Long Phú 1',
      ),
      45 =>
      array(
        'maphuongxa' => 81603046,
        'tenphuongxa' => 'Xã Thạnh Xuân',
      ),
      46 =>
      array(
        'maphuongxa' => 81603047,
        'tenphuongxa' => 'Xã Tân Hoà',
      ),
      47 =>
      array(
        'maphuongxa' => 81603048,
        'tenphuongxa' => 'Xã Trường Long Tây',
      ),
      48 =>
      array(
        'maphuongxa' => 81605049,
        'tenphuongxa' => 'Xã Châu Thành',
      ),
      49 =>
      array(
        'maphuongxa' => 81605050,
        'tenphuongxa' => 'Xã Đông Phước',
      ),
      50 =>
      array(
        'maphuongxa' => 81605051,
        'tenphuongxa' => 'Xã Phú Hữu',
      ),
      51 =>
      array(
        'maphuongxa' => 81607052,
        'tenphuongxa' => 'Phường Đại Thành',
      ),
      52 =>
      array(
        'maphuongxa' => 81607053,
        'tenphuongxa' => 'Phường Ngã Bảy',
      ),
      53 =>
      array(
        'maphuongxa' => 81608054,
        'tenphuongxa' => 'Xã Tân Bình',
      ),
      54 =>
      array(
        'maphuongxa' => 81608055,
        'tenphuongxa' => 'Xã Hoà An',
      ),
      55 =>
      array(
        'maphuongxa' => 81608056,
        'tenphuongxa' => 'Xã Phương Bình',
      ),
      56 =>
      array(
        'maphuongxa' => 81608057,
        'tenphuongxa' => 'Xã Tân Phước Hưng',
      ),
      57 =>
      array(
        'maphuongxa' => 81608058,
        'tenphuongxa' => 'Xã Hiệp Hưng',
      ),
      58 =>
      array(
        'maphuongxa' => 81608059,
        'tenphuongxa' => 'Xã Phụng Hiệp',
      ),
      59 =>
      array(
        'maphuongxa' => 81608060,
        'tenphuongxa' => 'Xã Thạnh Hoà',
      ),
      60 =>
      array(
        'maphuongxa' => 81901061,
        'tenphuongxa' => 'Phường Phú Lợi',
      ),
      61 =>
      array(
        'maphuongxa' => 81901062,
        'tenphuongxa' => 'Phường Sóc Trăng',
      ),
      62 =>
      array(
        'maphuongxa' => 81901063,
        'tenphuongxa' => 'Phường Mỹ Xuyên',
      ),
      63 =>
      array(
        'maphuongxa' => 81909064,
        'tenphuongxa' => 'Xã Hoà Tú',
      ),
      64 =>
      array(
        'maphuongxa' => 81909065,
        'tenphuongxa' => 'Xã Gia Hoà',
      ),
      65 =>
      array(
        'maphuongxa' => 81909066,
        'tenphuongxa' => 'Xã Nhu Gia',
      ),
      66 =>
      array(
        'maphuongxa' => 81909067,
        'tenphuongxa' => 'Xã Ngọc Tố',
      ),
      67 =>
      array(
        'maphuongxa' => 81905068,
        'tenphuongxa' => 'Xã Trường Khánh',
      ),
      68 =>
      array(
        'maphuongxa' => 81905069,
        'tenphuongxa' => 'Xã Đại Ngãi',
      ),
      69 =>
      array(
        'maphuongxa' => 81905070,
        'tenphuongxa' => 'Xã Tân Thạnh',
      ),
      70 =>
      array(
        'maphuongxa' => 81905071,
        'tenphuongxa' => 'Xã Long Phú',
      ),
      71 =>
      array(
        'maphuongxa' => 81903072,
        'tenphuongxa' => 'Xã Nhơn Mỹ',
      ),
      72 =>
      array(
        'maphuongxa' => 81903073,
        'tenphuongxa' => 'Xã Phong Nẫm',
      ),
      73 =>
      array(
        'maphuongxa' => 81903074,
        'tenphuongxa' => 'Xã An Lạc Thôn',
      ),
      74 =>
      array(
        'maphuongxa' => 81903075,
        'tenphuongxa' => 'Xã Kế Sách',
      ),
      75 =>
      array(
        'maphuongxa' => 81903076,
        'tenphuongxa' => 'Xã Thới An Hội',
      ),
      76 =>
      array(
        'maphuongxa' => 81903077,
        'tenphuongxa' => 'Xã Đại Hải',
      ),
      77 =>
      array(
        'maphuongxa' => 81915078,
        'tenphuongxa' => 'Xã Phú Tâm',
      ),
      78 =>
      array(
        'maphuongxa' => 81915079,
        'tenphuongxa' => 'Xã An Ninh',
      ),
      79 =>
      array(
        'maphuongxa' => 81915080,
        'tenphuongxa' => 'Xã Thuận Hoà',
      ),
      80 =>
      array(
        'maphuongxa' => 81915081,
        'tenphuongxa' => 'Xã Hồ Đắc Kiện',
      ),
      81 =>
      array(
        'maphuongxa' => 81907082,
        'tenphuongxa' => 'Xã Mỹ Tú',
      ),
      82 =>
      array(
        'maphuongxa' => 81907083,
        'tenphuongxa' => 'Xã Long Hưng',
      ),
      83 =>
      array(
        'maphuongxa' => 81907084,
        'tenphuongxa' => 'Xã Mỹ Phước',
      ),
      84 =>
      array(
        'maphuongxa' => 81907085,
        'tenphuongxa' => 'Xã Mỹ Hương',
      ),
      85 =>
      array(
        'maphuongxa' => 81913086,
        'tenphuongxa' => 'Xã Vĩnh Hải',
      ),
      86 =>
      array(
        'maphuongxa' => 81913087,
        'tenphuongxa' => 'Xã Lai Hoà',
      ),
      87 =>
      array(
        'maphuongxa' => 81913088,
        'tenphuongxa' => 'Phường Vĩnh Phước',
      ),
      88 =>
      array(
        'maphuongxa' => 81913089,
        'tenphuongxa' => 'Phường Vĩnh Châu',
      ),
      89 =>
      array(
        'maphuongxa' => 81913090,
        'tenphuongxa' => 'Phường Khánh Hoà',
      ),
      90 =>
      array(
        'maphuongxa' => 81912091,
        'tenphuongxa' => 'Xã Tân Long',
      ),
      91 =>
      array(
        'maphuongxa' => 81912092,
        'tenphuongxa' => 'Phường Ngã Năm',
      ),
      92 =>
      array(
        'maphuongxa' => 81912093,
        'tenphuongxa' => 'Phường Mỹ Quới',
      ),
      93 =>
      array(
        'maphuongxa' => 81911094,
        'tenphuongxa' => 'Xã Phú Lộc',
      ),
      94 =>
      array(
        'maphuongxa' => 81911095,
        'tenphuongxa' => 'Xã Vĩnh Lợi',
      ),
      95 =>
      array(
        'maphuongxa' => 81911096,
        'tenphuongxa' => 'Xã Lâm Tân',
      ),
      96 =>
      array(
        'maphuongxa' => 81917097,
        'tenphuongxa' => 'Xã Thạnh Thới An',
      ),
      97 =>
      array(
        'maphuongxa' => 81917098,
        'tenphuongxa' => 'Xã Tài Văn',
      ),
      98 =>
      array(
        'maphuongxa' => 81917099,
        'tenphuongxa' => 'Xã Liêu Tú',
      ),
      99 =>
      array(
        'maphuongxa' => 81917100,
        'tenphuongxa' => 'Xã Lịch Hội Thượng',
      ),
      100 =>
      array(
        'maphuongxa' => 81917101,
        'tenphuongxa' => 'Xã Trần Đề',
      ),
      101 =>
      array(
        'maphuongxa' => 81906102,
        'tenphuongxa' => 'Xã An Thạnh',
      ),
      102 =>
      array(
        'maphuongxa' => 81906103,
        'tenphuongxa' => 'Xã Cù Lao Dung',
      ),
    ),
  ),
  33 =>
  array(
    'matinhBNV' => 34,
    'matinhTMS' => '823',
    'tentinhmoi' => 'Tỉnh Cà Mau',
    'phuongxa' =>
    array(
      0 =>
      array(
        'maphuongxa' => 82301001,
        'tenphuongxa' => 'Phường An Xuyên',
      ),
      1 =>
      array(
        'maphuongxa' => 82301002,
        'tenphuongxa' => 'Phường Lý Văn Lâm',
      ),
      2 =>
      array(
        'maphuongxa' => 82301003,
        'tenphuongxa' => 'Phường Tân Thành',
      ),
      3 =>
      array(
        'maphuongxa' => 82301004,
        'tenphuongxa' => 'Phường Hòa Thành',
      ),
      4 =>
      array(
        'maphuongxa' => 82311005,
        'tenphuongxa' => 'Xã Tân Thuận',
      ),
      5 =>
      array(
        'maphuongxa' => 82311006,
        'tenphuongxa' => 'Xã Tân Tiến',
      ),
      6 =>
      array(
        'maphuongxa' => 82311007,
        'tenphuongxa' => 'Xã Tạ An Khương',
      ),
      7 =>
      array(
        'maphuongxa' => 82311008,
        'tenphuongxa' => 'Xã Trần Phán',
      ),
      8 =>
      array(
        'maphuongxa' => 82311009,
        'tenphuongxa' => 'Xã Thanh Tùng',
      ),
      9 =>
      array(
        'maphuongxa' => 82311010,
        'tenphuongxa' => 'Xã Đầm Dơi',
      ),
      10 =>
      array(
        'maphuongxa' => 82311011,
        'tenphuongxa' => 'Xã Quách Phẩm',
      ),
      11 =>
      array(
        'maphuongxa' => 82305012,
        'tenphuongxa' => 'Xã U Minh',
      ),
      12 =>
      array(
        'maphuongxa' => 82305013,
        'tenphuongxa' => 'Xã Nguyễn Phích',
      ),
      13 =>
      array(
        'maphuongxa' => 82305014,
        'tenphuongxa' => 'Xã Khánh Lâm',
      ),
      14 =>
      array(
        'maphuongxa' => 82305015,
        'tenphuongxa' => 'Xã Khánh An',
      ),
      15 =>
      array(
        'maphuongxa' => 82313016,
        'tenphuongxa' => 'Xã Phan Ngọc Hiển',
      ),
      16 =>
      array(
        'maphuongxa' => 82313017,
        'tenphuongxa' => 'Xã Đất Mũi',
      ),
      17 =>
      array(
        'maphuongxa' => 82313018,
        'tenphuongxa' => 'Xã Tân Ân',
      ),
      18 =>
      array(
        'maphuongxa' => 82307019,
        'tenphuongxa' => 'Xã Khánh Bình',
      ),
      19 =>
      array(
        'maphuongxa' => 82307020,
        'tenphuongxa' => 'Xã Đá Bạc',
      ),
      20 =>
      array(
        'maphuongxa' => 82307021,
        'tenphuongxa' => 'Xã Khánh Hưng',
      ),
      21 =>
      array(
        'maphuongxa' => 82307022,
        'tenphuongxa' => 'Xã Sông Đốc',
      ),
      22 =>
      array(
        'maphuongxa' => 82307023,
        'tenphuongxa' => 'Xã Trần Văn Thời',
      ),
      23 =>
      array(
        'maphuongxa' => 82303024,
        'tenphuongxa' => 'Xã Thới Bình',
      ),
      24 =>
      array(
        'maphuongxa' => 82303025,
        'tenphuongxa' => 'Xã Trí Phải',
      ),
      25 =>
      array(
        'maphuongxa' => 82303026,
        'tenphuongxa' => 'Xã Tân Lộc',
      ),
      26 =>
      array(
        'maphuongxa' => 82303027,
        'tenphuongxa' => 'Xã Hồ Thị Kỷ',
      ),
      27 =>
      array(
        'maphuongxa' => 82303028,
        'tenphuongxa' => 'Xã Biển Bạch',
      ),
      28 =>
      array(
        'maphuongxa' => 82312029,
        'tenphuongxa' => 'Xã Đất Mới',
      ),
      29 =>
      array(
        'maphuongxa' => 82312030,
        'tenphuongxa' => 'Xã Năm Căn',
      ),
      30 =>
      array(
        'maphuongxa' => 82312031,
        'tenphuongxa' => 'Xã Tam Giang',
      ),
      31 =>
      array(
        'maphuongxa' => 82308032,
        'tenphuongxa' => 'Xã Cái Đôi Vàm',
      ),
      32 =>
      array(
        'maphuongxa' => 82308033,
        'tenphuongxa' => 'Xã Nguyễn Việt Khái',
      ),
      33 =>
      array(
        'maphuongxa' => 82308034,
        'tenphuongxa' => 'Xã Phú Tân',
      ),
      34 =>
      array(
        'maphuongxa' => 82308035,
        'tenphuongxa' => 'Xã Phú Mỹ',
      ),
      35 =>
      array(
        'maphuongxa' => 82309036,
        'tenphuongxa' => 'Xã Lương Thế Trân',
      ),
      36 =>
      array(
        'maphuongxa' => 82309037,
        'tenphuongxa' => 'Xã Tân Hưng',
      ),
      37 =>
      array(
        'maphuongxa' => 82309038,
        'tenphuongxa' => 'Xã Hưng Mỹ',
      ),
      38 =>
      array(
        'maphuongxa' => 82309039,
        'tenphuongxa' => 'Xã Cái Nước',
      ),
      39 =>
      array(
        'maphuongxa' => 82101040,
        'tenphuongxa' => 'Phường Bạc Liêu',
      ),
      40 =>
      array(
        'maphuongxa' => 82101041,
        'tenphuongxa' => 'Phường Vĩnh Trạch',
      ),
      41 =>
      array(
        'maphuongxa' => 82101042,
        'tenphuongxa' => 'Phường Hiệp Thành',
      ),
      42 =>
      array(
        'maphuongxa' => 82107043,
        'tenphuongxa' => 'Phường Giá Rai',
      ),
      43 =>
      array(
        'maphuongxa' => 82107044,
        'tenphuongxa' => 'Phường Láng Tròn',
      ),
      44 =>
      array(
        'maphuongxa' => 82107045,
        'tenphuongxa' => 'Xã Phong Thạnh',
      ),
      45 =>
      array(
        'maphuongxa' => 82103046,
        'tenphuongxa' => 'Xã Hồng Dân',
      ),
      46 =>
      array(
        'maphuongxa' => 82103047,
        'tenphuongxa' => 'Xã Vĩnh Lộc',
      ),
      47 =>
      array(
        'maphuongxa' => 82103048,
        'tenphuongxa' => 'Xã Ninh Thạnh Lợi',
      ),
      48 =>
      array(
        'maphuongxa' => 82103049,
        'tenphuongxa' => 'Xã Ninh Quới',
      ),
      49 =>
      array(
        'maphuongxa' => 82111050,
        'tenphuongxa' => 'Xã Gành Hào',
      ),
      50 =>
      array(
        'maphuongxa' => 82111051,
        'tenphuongxa' => 'Xã Định Thành',
      ),
      51 =>
      array(
        'maphuongxa' => 82111052,
        'tenphuongxa' => 'Xã An Trạch',
      ),
      52 =>
      array(
        'maphuongxa' => 82111053,
        'tenphuongxa' => 'Xã Long Điền',
      ),
      53 =>
      array(
        'maphuongxa' => 82111054,
        'tenphuongxa' => 'Xã Đông Hải',
      ),
      54 =>
      array(
        'maphuongxa' => 82106055,
        'tenphuongxa' => 'Xã Hoà Bình',
      ),
      55 =>
      array(
        'maphuongxa' => 82106056,
        'tenphuongxa' => 'Xã Vĩnh Mỹ',
      ),
      56 =>
      array(
        'maphuongxa' => 82106057,
        'tenphuongxa' => 'Xã Vĩnh Hậu',
      ),
      57 =>
      array(
        'maphuongxa' => 82109058,
        'tenphuongxa' => 'Xã Phước Long',
      ),
      58 =>
      array(
        'maphuongxa' => 82109059,
        'tenphuongxa' => 'Xã Vĩnh Phước',
      ),
      59 =>
      array(
        'maphuongxa' => 82109060,
        'tenphuongxa' => 'Xã Phong Hiệp',
      ),
      60 =>
      array(
        'maphuongxa' => 82109061,
        'tenphuongxa' => 'Xã Vĩnh Thanh',
      ),
      61 =>
      array(
        'maphuongxa' => 82105062,
        'tenphuongxa' => 'Xã Vĩnh Lợi',
      ),
      62 =>
      array(
        'maphuongxa' => 82105063,
        'tenphuongxa' => 'Xã Hưng Hội',
      ),
      63 =>
      array(
        'maphuongxa' => 82105064,
        'tenphuongxa' => 'Xã Châu Thới',
      ),
    ),
  ),
        );
    }
}

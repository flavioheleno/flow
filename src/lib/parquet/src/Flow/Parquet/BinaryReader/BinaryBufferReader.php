<?php declare(strict_types=1);

namespace Flow\Parquet\BinaryReader;

use Flow\Parquet\BinaryReader;
use Flow\Parquet\ByteOrder;
use Flow\Parquet\DataSize;

final class BinaryBufferReader implements BinaryReader
{
    private readonly DataSize $length;

    private DataSize $position;

    private DataSize $remainingLength;

    public function __construct(private readonly string $buffer, private readonly ByteOrder $byteOrder = ByteOrder::LITTLE_ENDIAN)
    {
        $this->position = new DataSize(0);
        $bits = \strlen($buffer) * 8;
        $this->remainingLength = new DataSize($bits);
        $this->length = new DataSize($bits);
    }

    public function length() : DataSize
    {
        return $this->length;
    }

    public function position() : DataSize
    {
        return $this->position;
    }

    public function readBit() : int
    {
        $bytePosition = $this->position->bytes();
        $bitOffset = $this->position->bits();

        $byte = \ord($this->buffer[$bytePosition]);
        $bit = ($byte >> $bitOffset) & 1;

        $this->position->add(1);
        $this->remainingLength->sub(1);

        return $bit;
    }

    /**
     * @return array<int>
     */
    public function readBits(int $total) : array
    {
        $bits = [];
        $bytePosition = $this->position()->bytes();
        $bitOffset = $this->position->bits() % 8;
        $bytesNeeded = \intdiv($bitOffset + $total - 1, 8) + 1;
        $currentBytes = \substr($this->buffer, $bytePosition, $bytesNeeded);

        for ($i = 0; $i < $bytesNeeded; $i++) {
            $byte = \ord($currentBytes[$i] ?? '');

            for ($j = $bitOffset; $j < 8; $j++) {
                $bits[] = ($byte >> $j) & 1;

                if (--$total === 0) {
                    $this->position->add($i * 8 + $j + 1 - $bitOffset);
                    $this->remainingLength->sub($i * 8 + $j + 1 - $bitOffset);

                    return $bits;
                }
            }
            $bitOffset = 0;
        }

        return $bits;  // This should never be reached
    }

    public function readBoolean() : bool
    {
        return (bool) $this->readBit();
    }

    public function readBooleans(int $total) : array
    {
        $bits = $this->readBits($total);
        $booleans = [];

        foreach ($bits as $bit) {
            $booleans[] = (bool) $bit;
        }

        return $booleans;
    }

    public function readByte() : int
    {
        $byte = \ord($this->buffer[$this->position()->bytes()]);
        $this->position->add(8);
        $this->remainingLength->sub(8);

        return $byte;
    }

    public function readByteArrays(int $total) : array
    {
        $position = $this->position()->bytes();
        $byteArrays = [];

        while (\count($byteArrays) < $total) {
            $rawStr = \substr($this->buffer, $position, 4);

            if ($rawStr === '') {
                break;
            }
            // Read the length of the string from the first byte
            $bytesLength = \unpack($this->byteOrder === ByteOrder::LITTLE_ENDIAN ? 'V' : 'N', $rawStr)[1];
            $position += 4;

            $byteStr = \substr($this->buffer, $position, $bytesLength);
            $byteArray = [];

            for ($i = 0; $i < $bytesLength; $i++) {
                $byteArray[] = \ord($byteStr[$i]);
            }

            $byteArrays[] = $byteArray;
            $position += $bytesLength;
        }

        $this->position->add($position * 8);
        $this->remainingLength->sub($position * 8);

        return $byteArrays;
    }

    public function readBytes(int $total) : Bytes
    {
        $bytes = \array_values(\unpack('C*', \substr($this->buffer, $this->position()->bytes(), $total)));

        $this->position->add(8 * $total);
        $this->remainingLength->sub(8 * $total);

        return new Bytes($bytes);
    }

    public function readDouble() : float
    {
        $result = \unpack(
            $this->byteOrder === ByteOrder::LITTLE_ENDIAN ? 'e' : 'E',
            \substr($this->buffer, $this->position()->bytes(), 8)
        )[1];

        $this->position->add(64);
        $this->remainingLength->sub(64);

        return $result;
    }

    public function readDoubles(int $total) : array
    {
        $doubleBytes = \array_chunk($this->readBytes(8 * $total)->toArray(), 8);

        $doubles = [];

        foreach ($doubleBytes as $bytes) {
            $doubles[] = \unpack($this->byteOrder === ByteOrder::LITTLE_ENDIAN ? 'e' : 'E', \pack('C*', ...$bytes))[1];
        }

        return $doubles;
    }

    public function readFloat() : float
    {
        $result = \unpack(
            $this->byteOrder === ByteOrder::LITTLE_ENDIAN ? 'g' : 'G',
            \substr($this->buffer, $this->position()->bytes(), 4)
        )[1];

        $this->position->add(32);
        $this->remainingLength->sub(32);

        return $result;
    }

    public function readFloats(int $total) : array
    {
        $floatBytes = \array_chunk($this->readBytes(4 * $total)->toArray(), 4);

        $floats = [];

        foreach ($floatBytes as $bytes) {
            $floats[] = \unpack($this->byteOrder === ByteOrder::LITTLE_ENDIAN ? 'g' : 'G', \pack('C*', ...$bytes))[1];
        }

        return $floats;
    }

    public function readInt32() : int
    {
        $bytes = $this->readBytes(4)->toArray();

        if ($this->byteOrder === ByteOrder::LITTLE_ENDIAN) {
            return $bytes[0] | ($bytes[1] << 8) | ($bytes[2] << 16) | ($bytes[3] << 24);
        }

        return ($bytes[0] << 24) | ($bytes[1] << 16) | ($bytes[2] << 8) | $bytes[3];
    }

    public function readInt64() : int
    {
        $bytes = $this->readBytes(8)->toArray();

        if ($this->byteOrder === ByteOrder::LITTLE_ENDIAN) {
            return $bytes[0] | ($bytes[1] << 8) | ($bytes[2] << 16) | ($bytes[3] << 24) |
                ($bytes[4] << 32) | ($bytes[5] << 40) | ($bytes[6] << 48) | ($bytes[7] << 56);
        }

        return ($bytes[0] << 56) | ($bytes[1] << 48) | ($bytes[2] << 40) | ($bytes[3] << 32) |
            ($bytes[4] << 24) | ($bytes[5] << 16) | ($bytes[6] << 8) | $bytes[7];
    }

    public function readInt96() : array
    {
        $position = $this->position()->bytes();
        $data = \substr($this->buffer, $position, 12);

        $int96Bytes = [];

        foreach (\str_split($data) as $byte) {
            $int96Bytes[] = \ord($byte);
        }

        $this->position->add(12 * 8);
        $this->remainingLength->sub(12 * 8);

        return $int96Bytes;
    }

    /**
     * @return array<int>
     */
    public function readInts32(int $total) : array
    {
        $intBytes = \array_chunk($this->readBytes(4 * $total)->toArray(), 4);
        $ints = [];

        foreach ($intBytes as $bytes) {
            if ($this->byteOrder === ByteOrder::LITTLE_ENDIAN) {
                $ints[] = $bytes[0] | ($bytes[1] << 8) | ($bytes[2] << 16) | ($bytes[3] << 24);
            } else {
                $ints[] = ($bytes[0] << 24) | ($bytes[1] << 16) | ($bytes[2] << 8) | $bytes[3];
            }
        }

        return $ints;
    }

    public function readInts64(int $total) : array
    {
        $intBytes = \array_chunk($this->readBytes(8 * $total)->toArray(), 8);

        $ints = [];

        foreach ($intBytes as $bytes) {
            if ($this->byteOrder === ByteOrder::LITTLE_ENDIAN) {
                $ints[] = $bytes[0] | ($bytes[1] << 8) | ($bytes[2] << 16) | ($bytes[3] << 24) |
                    ($bytes[4] << 32) | ($bytes[5] << 40) | ($bytes[6] << 48) | ($bytes[7] << 56);
            } else {
                $ints[] = ($bytes[0] << 56) | ($bytes[1] << 48) | ($bytes[2] << 40) | ($bytes[3] << 32) |
                    ($bytes[4] << 24) | ($bytes[5] << 16) | ($bytes[6] << 8) | $bytes[7];
            }
        }

        return $ints;
    }

    public function readInts96(int $total) : array
    {
        $intsData = \substr($this->buffer, $this->position()->bytes(), 12 * $total);

        $ints96 = [];

        foreach (\str_split($intsData, 12) as $data) {
            $int96Bytes = [];

            foreach (\str_split($data) as $byte) {
                $int96Bytes[] = \ord($byte);
            }

            $ints96[] = $int96Bytes;
        }

        $this->position->add(12 * $total * 8);
        $this->remainingLength->sub(12 * $total * 8);

        return $ints96;
    }

    public function readString() : string
    {
        $length = $this->readInt32();
        $string = \substr($this->buffer, $this->position()->bytes(), $length);
        $this->position->add($length * 8);
        $this->remainingLength->sub($length * 8);

        return $string;
    }

    public function readStrings(int $total) : array
    {
        $position = $this->position()->bytes();
        $strings = [];

        while (\count($strings) < $total) {
            $rawStr = \substr($this->buffer, $position, 4);

            if ($rawStr === '') {
                break;
            }
            // Read the length of the string from the first byte
            $strLength = \unpack($this->byteOrder === ByteOrder::LITTLE_ENDIAN ? 'V' : 'N', $rawStr)[1];
            $position += 4;

            // Read the string based on the length
            $strings[] = \substr($this->buffer, $position, $strLength);
            $position += $strLength;
        }

        $this->position->add($position * 8);
        $this->remainingLength->sub($position * 8);

        return $strings;
    }

    public function readUInt32() : int
    {
        $bytes = $this->readBytes(4)->toArray();

        if ($this->byteOrder === ByteOrder::LITTLE_ENDIAN) {
            return $bytes[0] | ($bytes[1] << 8) | ($bytes[2] << 16) | ($bytes[3] << 24);
        }

        return ($bytes[0] << 24) | ($bytes[1] << 16) | ($bytes[2] << 8) | $bytes[3];
    }

    public function readUInt64() : int
    {
        return $this->readInt64();
    }

    public function readUInts32(int $total) : array
    {
        $intBytes = \array_chunk($this->readBytes(4 * $total)->toArray(), 4);

        $ints = [];

        foreach ($intBytes as $bytes) {
            if ($this->byteOrder === ByteOrder::LITTLE_ENDIAN) {
                $ints[] = $bytes[0] | ($bytes[1] << 8) | ($bytes[2] << 16) | ($bytes[3] << 24);
            } else {
                $ints[] = ($bytes[0] << 24) | ($bytes[1] << 16) | ($bytes[2] << 8) | $bytes[3];
            }
        }

        return $ints;
    }

    public function readUInts64(int $total) : array
    {
        $intBytes = \array_chunk($this->readBytes(4 * $total)->toArray(), 4);

        $ints = [];

        foreach ($intBytes as $bytes) {
            if ($this->byteOrder === ByteOrder::LITTLE_ENDIAN) {
                $ints[] = $bytes[0] | ($bytes[1] << 8) | ($bytes[2] << 16) | ($bytes[3] << 24) |
                    ($bytes[4] << 32) | ($bytes[5] << 40) | ($bytes[6] << 48) | ($bytes[7] << 56);
            } else {
                $ints[] = ($bytes[0] << 56) | ($bytes[1] << 48) | ($bytes[2] << 40) | ($bytes[3] << 32) |
                    ($bytes[4] << 24) | ($bytes[5] << 16) | ($bytes[6] << 8) | $bytes[7];
            }
        }

        return $ints;
    }

    public function readVarInt() : int
    {
        $result = 0;
        $shift = 0;

        do {
            $byte = $this->readByte();
            $result |= ($byte & 0x7F) << $shift;
            $shift += 7;
        } while ($byte >= 0x80);

        return $result;
    }

    public function remainingLength() : DataSize
    {
        return $this->remainingLength;
    }

    public function seekBits(int $bits) : void
    {
        $this->position->add($bits);
        $this->length->sub($bits);
    }

    public function seekBytes(int $bytes) : void
    {
        $this->position->add($bytes * 8);
        $this->remainingLength->sub($bytes * 8);
    }
}
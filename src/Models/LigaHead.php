<?php

namespace PadelClub\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Liga HEAD: liga por parejas, todos contra todos, una jornada por semana.
 *
 * Tabla: ligas_head
 */
class LigaHead extends Model
{
    protected $table = 'ligas_head';
    protected $primaryKey = 'id';

    public $timestamps = true;

    protected $fillable = [
        'nombre', 'genero', 'categoria', 'precio', 'plazas_max', 'plazas_min',
        'inscripcion_desde', 'inscripcion_hasta', 'fecha_inicio', 'ida_vuelta',
        'jornadas', 'reglamento', 'estado', 'creado_por',
    ];

    protected $casts = [
        'precio'     => 'float',
        'plazas_max' => 'integer',
        'plazas_min' => 'integer',
        'ida_vuelta' => 'boolean',
        'jornadas'   => 'integer',
        'creado_por' => 'integer',
    ];

    public function categorias()
    {
        return $this->hasMany(LigaHeadCategoria::class, 'liga_id')->orderBy('categoria');
    }

    /** Lista simple de categorías de la liga. */
    public function categoriasLista(): array
    {
        $lista = $this->categorias()->pluck('categoria')->all();
        if (empty($lista) && !empty($this->categoria)) {
            $lista = [$this->categoria];
        }
        return $lista;
    }

    public function jornadas()
    {
        return $this->hasMany(LigaHeadJornada::class, 'liga_id')->orderBy('numero');
    }

    public function parejas()
    {
        return $this->hasMany(LigaHeadPareja::class, 'liga_id');
    }

    public function partidos()
    {
        return $this->hasMany(LigaHeadPartido::class, 'liga_id');
    }

    /** Parejas que cuentan para el cuadro (confirmadas). */
    public function parejasConfirmadas()
    {
        return $this->parejas()->where('estado', 'confirmada');
    }

    public function inscripcionesAbiertas(): bool
    {
        return $this->estado === 'inscripcion'
            && date('Y-m-d') >= $this->inscripcion_desde
            && date('Y-m-d') <= $this->inscripcion_hasta;
    }

    /** Pareja del jugador dentro de esta liga, si la tiene. */
    public function parejaDe($jugadorId)
    {
        return $this->parejas()
            ->where(function ($q) use ($jugadorId) {
                $q->where('jugador1_id', $jugadorId)->orWhere('jugador2_id', $jugadorId);
            })
            ->whereIn('estado', ['pendiente', 'confirmada'])
            ->first();
    }
}

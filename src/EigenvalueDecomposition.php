<?php
namespace vladkolodka\linearAlgebra;
class EigenvalueDecomposition {
    /**
     *    Row and column dimension (square matrix).
     * @var int
     */
    private $n;

    /**
     *    Arrays for internal storage of eigenvalues.
     * @var array
     */
    private $d = array();
    private $e = array();

    /**
     *    Array for internal storage of eigen vectors.
     * @var array
     */
    private $V = array();

    /**
     *    Array for internal storage of non-symmetric Hessenberg form.
     * @var array
     */
    private $H = array();

    /**
     *    Working storage for non-symmetric algorithm.
     * @var array
     */
    private $ort;

    /**
     *    Used for complex scalar division.
     * @var float
     */
    private $cDivR;
    private $cDivI;

    /**
     *    Symmetric Householder reduction to tri-diagonal form.
     *
     * @access private
     */
    private function tRed() {

        $this->d = $this->V[$this->n - 1];
        // Householder reduction to tri-diagonal form.
        for ($i = $this->n - 1; $i > 0; --$i) {
            $i_ = $i - 1;
            // Scale to avoid under/overflow.
            $h = $scale = 0.0;
            $scale += array_sum(array_map('abs', $this->d));
            if ($scale == 0.0) {
                $this->e[$i] = $this->d[$i_];
                $this->d = array_slice($this->V[$i_], 0, $i_);
                for ($j = 0; $j < $i; ++$j) {
                    $this->V[$j][$i] = $this->V[$i][$j] = 0.0;
                }
            } else {
                // Generate Householder vector.
                for ($k = 0; $k < $i; ++$k) {
                    $this->d[$k] /= $scale;
                    $h += pow($this->d[$k], 2);
                }
                $f = $this->d[$i_];
                $g = sqrt($h);
                if ($f > 0) {
                    $g = -$g;
                }
                $this->e[$i] = $scale * $g;
                $h = $h - $f * $g;
                $this->d[$i_] = $f - $g;
                for ($j = 0; $j < $i; ++$j) {
                    $this->e[$j] = 0.0;
                }
                // Apply similarity transformation to remaining columns.
                for ($j = 0; $j < $i; ++$j) {
                    $f = $this->d[$j];
                    $this->V[$j][$i] = $f;
                    $g = $this->e[$j] + $this->V[$j][$j] * $f;
                    for ($k = $j + 1; $k <= $i_; ++$k) {
                        $g += $this->V[$k][$j] * $this->d[$k];
                        $this->e[$k] += $this->V[$k][$j] * $f;
                    }
                    $this->e[$j] = $g;
                }
                $f = 0.0;
                for ($j = 0; $j < $i; ++$j) {
                    $this->e[$j] /= $h;
                    $f += $this->e[$j] * $this->d[$j];
                }
                $hh = $f / (2 * $h);
                for ($j = 0; $j < $i; ++$j) {
                    $this->e[$j] -= $hh * $this->d[$j];
                }
                for ($j = 0; $j < $i; ++$j) {
                    $f = $this->d[$j];
                    $g = $this->e[$j];
                    for ($k = $j; $k <= $i_; ++$k) {
                        $this->V[$k][$j] -= ($f * $this->e[$k] + $g * $this->d[$k]);
                    }
                    $this->d[$j] = $this->V[$i - 1][$j];
                    $this->V[$i][$j] = 0.0;
                }
            }
            $this->d[$i] = $h;
        }
        $j = 0;
        // Accumulate transformations.
        for ($i = 0; $i < $this->n - 1; ++$i) {
            $this->V[$this->n - 1][$i] = $this->V[$i][$i];
            $this->V[$i][$i] = 1.0;
            $h = $this->d[$i + 1];
            if ($h != 0.0) {
                for ($k = 0; $k <= $i; ++$k) {
                    $this->d[$k] = $this->V[$k][$i + 1] / $h;
                }
                for ($j = 0; $j <= $i; ++$j) {
                    $g = 0.0;
                    for ($k = 0; $k <= $i; ++$k) {
                        $g += $this->V[$k][$i + 1] * $this->V[$k][$j];
                    }
                    for ($k = 0; $k <= $i; ++$k) {
                        $this->V[$k][$j] -= $g * $this->d[$k];
                    }
                }
            }
            for ($k = 0; $k <= $i; ++$k) {
                $this->V[$k][$i + 1] = 0.0;
            }
        }

        $this->d = $this->V[$this->n - 1];
        $this->V[$this->n - 1] = array_fill(0, $j, 0.0);
        $this->V[$this->n - 1][$this->n - 1] = 1.0;
        $this->e[0] = 0.0;
    }

    /**
     *    Symmetric tri-diagonal QL algorithm.
     *
     *    This is derived from the Algol procedures tql2, by
     *    Bowdler, Martin, Reinsch, and Wilkinson, Handbook for
     *    Auto. Comp., Vol.ii-Linear Algebra, and the corresponding
     *    Fortran subroutine in EISPACK.
     *
     * @access private
     */
    private function tql2() {
        for ($i = 1; $i < $this->n; ++$i) {
            $this->e[$i - 1] = $this->e[$i];
        }
        $this->e[$this->n - 1] = 0.0;
        $f = 0.0;
        $tst1 = 0.0;
        $eps = pow(2.0, -52.0);

        for ($l = 0; $l < $this->n; ++$l) {
            // Find small sub-diagonal element
            $tst1 = max($tst1, abs($this->d[$l]) + abs($this->e[$l]));
            $m = $l;
            while ($m < $this->n) {
                if (abs($this->e[$m]) <= $eps * $tst1) {
                    break;
                }
                ++$m;
            }
            // If m == l, $this->d[l] is an eigenvalue,
            // otherwise, iterate.
            if ($m > $l) {
                $iterator = 0;
                do {
                    // Could check iteration count here.
                    $iterator += 1;
                    // Compute implicit shift
                    $g = $this->d[$l];
                    $p = ($this->d[$l + 1] - $g) / (2.0 * $this->e[$l]);
                    $r = Matrix::hypo($p, 1.0);
                    if ($p < 0) {
                        $r *= -1;
                    }
                    $this->d[$l] = $this->e[$l] / ($p + $r);
                    $this->d[$l + 1] = $this->e[$l] * ($p + $r);
                    $dl1 = $this->d[$l + 1];
                    $h = $g - $this->d[$l];
                    for ($i = $l + 2; $i < $this->n; ++$i) {
                        $this->d[$i] -= $h;
                    }
                    $f += $h;
                    // Implicit QL transformation.
                    $p = $this->d[$m];
                    $c = 1.0;
                    $c2 = $c3 = $c;
                    $el1 = $this->e[$l + 1];
                    $s = $s2 = 0.0;
                    for ($i = $m - 1; $i >= $l; --$i) {
                        $c3 = $c2;
                        $c2 = $c;
                        $s2 = $s;
                        $g = $c * $this->e[$i];
                        $h = $c * $p;
                        $r = Matrix::hypo($p, $this->e[$i]);
                        $this->e[$i + 1] = $s * $r;
                        $s = $this->e[$i] / $r;
                        $c = $p / $r;
                        $p = $c * $this->d[$i] - $s * $g;
                        $this->d[$i + 1] = $h + $s * ($c * $g + $s * $this->d[$i]);
                        // Accumulate transformation.
                        for ($k = 0; $k < $this->n; ++$k) {
                            $h = $this->V[$k][$i + 1];
                            $this->V[$k][$i + 1] = $s * $this->V[$k][$i] + $c * $h;
                            $this->V[$k][$i] = $c * $this->V[$k][$i] - $s * $h;
                        }
                    }
                    $p = -$s * $s2 * $c3 * $el1 * $this->e[$l] / $dl1;
                    $this->e[$l] = $s * $p;
                    $this->d[$l] = $c * $p;
                    // Check for convergence.
                } while (abs($this->e[$l]) > $eps * $tst1);
            }
            $this->d[$l] = $this->d[$l] + $f;
            $this->e[$l] = 0.0;
        }

        // Sort eigenvalues and corresponding vectors.
        for ($i = 0; $i < $this->n - 1; ++$i) {
            $k = $i;
            $p = $this->d[$i];
            for ($j = $i + 1; $j < $this->n; ++$j) {
                if ($this->d[$j] < $p) {
                    $k = $j;
                    $p = $this->d[$j];
                }
            }
            if ($k != $i) {
                $this->d[$k] = $this->d[$i];
                $this->d[$i] = $p;
                for ($j = 0; $j < $this->n; ++$j) {
                    $p = $this->V[$j][$i];
                    $this->V[$j][$i] = $this->V[$j][$k];
                    $this->V[$j][$k] = $p;
                }
            }
        }
    }

    /**
     *    Non-symmetric reduction to Hessenberg form.
     *
     *    This is derived from the Algol procedures ortHes and ortran,
     *    by Martin and Wilkinson, Handbook for Auto. Comp.,
     *    Vol.ii-Linear Algebra, and the corresponding
     *    Fortran subroutines in EISPACK.
     *
     * @access private
     */
    private function ortHes() {
        $low = 0;
        $high = $this->n - 1;

        for ($m = $low + 1; $m <= $high - 1; ++$m) {
            // Scale column.
            $scale = 0.0;
            for ($i = $m; $i <= $high; ++$i) {
                $scale = $scale + abs($this->H[$i][$m - 1]);
            }
            if ($scale != 0.0) {
                // Compute Householder transformation.
                $h = 0.0;
                for ($i = $high; $i >= $m; --$i) {
                    $this->ort[$i] = $this->H[$i][$m - 1] / $scale;
                    $h += $this->ort[$i] * $this->ort[$i];
                }
                $g = sqrt($h);
                if ($this->ort[$m] > 0) {
                    $g *= -1;
                }
                $h -= $this->ort[$m] * $g;
                $this->ort[$m] -= $g;
                // Apply Householder similarity transformation
                // H = (I -u * u' / h) * H * (I -u * u') / h)
                for ($j = $m; $j < $this->n; ++$j) {
                    $f = 0.0;
                    for ($i = $high; $i >= $m; --$i) {
                        $f += $this->ort[$i] * $this->H[$i][$j];
                    }
                    $f /= $h;
                    for ($i = $m; $i <= $high; ++$i) {
                        $this->H[$i][$j] -= $f * $this->ort[$i];
                    }
                }
                for ($i = 0; $i <= $high; ++$i) {
                    $f = 0.0;
                    for ($j = $high; $j >= $m; --$j) {
                        $f += $this->ort[$j] * $this->H[$i][$j];
                    }
                    $f = $f / $h;
                    for ($j = $m; $j <= $high; ++$j) {
                        $this->H[$i][$j] -= $f * $this->ort[$j];
                    }
                }
                $this->ort[$m] = $scale * $this->ort[$m];
                $this->H[$m][$m - 1] = $scale * $g;
            }
        }

        // Accumulate transformations (Algol's ortran).
        for ($i = 0; $i < $this->n; ++$i) {
            for ($j = 0; $j < $this->n; ++$j) {
                $this->V[$i][$j] = ($i == $j ? 1.0 : 0.0);
            }
        }
        for ($m = $high - 1; $m >= $low + 1; --$m) {
            if ($this->H[$m][$m - 1] != 0.0) {
                for ($i = $m + 1; $i <= $high; ++$i) {
                    $this->ort[$i] = $this->H[$i][$m - 1];
                }
                for ($j = $m; $j <= $high; ++$j) {
                    $g = 0.0;
                    for ($i = $m; $i <= $high; ++$i) {
                        $g += $this->ort[$i] * $this->V[$i][$j];
                    }
                    // Double division avoids possible underflow
                    $g = ($g / $this->ort[$m]) / $this->H[$m][$m - 1];
                    for ($i = $m; $i <= $high; ++$i) {
                        $this->V[$i][$j] += $g * $this->ort[$i];
                    }
                }
            }
        }
    }

    /**
     *    Performs complex division.
     *
     * @access private
     * @param $xr
     * @param $xi
     * @param $yr
     * @param $yi
     */
    private function cDiv($xr, $xi, $yr, $yi) {
        if (abs($yr) > abs($yi)) {
            $r = $yi / $yr;
            $d = $yr + $r * $yi;
            $this->cDivR = ($xr + $r * $xi) / $d;
            $this->cDivI = ($xi - $r * $xr) / $d;
        } else {
            $r = $yr / $yi;
            $d = $yi + $r * $yr;
            $this->cDivR = ($r * $xr + $xi) / $d;
            $this->cDivI = ($r * $xi - $xr) / $d;
        }
    }

    /**
     *    Non-symmetric reduction from Hessenberg to real Schur form.
     *
     *    Code is derived from the Algol procedure hqr2,
     *    by Martin and Wilkinson, Handbook for Auto. Comp.,
     *    Vol.ii-Linear Algebra, and the corresponding
     *    Fortran subroutine in EISPACK.
     *
     * @access private
     */
    private function hqr2() {
        //  Initialize
        $nn = $this->n;
        $n = $nn - 1;
        $low = 0;
        $high = $nn - 1;
        $eps = pow(2.0, -52.0);
        $exShift = 0.0;
        $p = $q = $r = $s = $z = 0;
        // Store roots isolated by balance and compute matrix norm
        $norm = 0.0;

        for ($i = 0; $i < $nn; ++$i) {
            if (($i < $low) or ($i > $high)) {
                $this->d[$i] = $this->H[$i][$i];
                $this->e[$i] = 0.0;
            }
            for ($j = max($i - 1, 0); $j < $nn; ++$j) {
                $norm = $norm + abs($this->H[$i][$j]);
            }
        }

        // Outer loop over eigenvalue index
        $iterator = 0;
        while ($n >= $low) {
            // Look for single small sub-diagonal element
            $l = $n;
            while ($l > $low) {
                $s = abs($this->H[$l - 1][$l - 1]) + abs($this->H[$l][$l]);
                if ($s == 0.0) {
                    $s = $norm;
                }
                if (abs($this->H[$l][$l - 1]) < $eps * $s) {
                    break;
                }
                --$l;
            }
            // Check for convergence
            // One root found
            if ($l == $n) {
                $this->H[$n][$n] = $this->H[$n][$n] + $exShift;
                $this->d[$n] = $this->H[$n][$n];
                $this->e[$n] = 0.0;
                --$n;
                $iterator = 0;
                // Two roots found
            } elseif ($l == $n - 1) {
                $w = $this->H[$n][$n - 1] * $this->H[$n - 1][$n];
                $p = ($this->H[$n - 1][$n - 1] - $this->H[$n][$n]) / 2.0;
                $q = $p * $p + $w;
                $z = sqrt(abs($q));
                $this->H[$n][$n] = $this->H[$n][$n] + $exShift;
                $this->H[$n - 1][$n - 1] = $this->H[$n - 1][$n - 1] + $exShift;
                $x = $this->H[$n][$n];
                // Real pair
                if ($q >= 0) {
                    if ($p >= 0) {
                        $z = $p + $z;
                    } else {
                        $z = $p - $z;
                    }
                    $this->d[$n - 1] = $x + $z;
                    $this->d[$n] = $this->d[$n - 1];
                    if ($z != 0.0) {
                        $this->d[$n] = $x - $w / $z;
                    }
                    $this->e[$n - 1] = 0.0;
                    $this->e[$n] = 0.0;
                    $x = $this->H[$n][$n - 1];
                    $s = abs($x) + abs($z);
                    $p = $x / $s;
                    $q = $z / $s;
                    $r = sqrt($p * $p + $q * $q);
                    $p = $p / $r;
                    $q = $q / $r;
                    // Row modification
                    for ($j = $n - 1; $j < $nn; ++$j) {
                        $z = $this->H[$n - 1][$j];
                        $this->H[$n - 1][$j] = $q * $z + $p * $this->H[$n][$j];
                        $this->H[$n][$j] = $q * $this->H[$n][$j] - $p * $z;
                    }
                    // Column modification
                    for ($i = 0; $i <= $n; ++$i) {
                        $z = $this->H[$i][$n - 1];
                        $this->H[$i][$n - 1] = $q * $z + $p * $this->H[$i][$n];
                        $this->H[$i][$n] = $q * $this->H[$i][$n] - $p * $z;
                    }
                    // Accumulate transformations
                    for ($i = $low; $i <= $high; ++$i) {
                        $z = $this->V[$i][$n - 1];
                        $this->V[$i][$n - 1] = $q * $z + $p * $this->V[$i][$n];
                        $this->V[$i][$n] = $q * $this->V[$i][$n] - $p * $z;
                    }
                    // Complex pair
                } else {
                    $this->d[$n - 1] = $x + $p;
                    $this->d[$n] = $x + $p;
                    $this->e[$n - 1] = $z;
                    $this->e[$n] = -$z;
                }
                $n = $n - 2;
                $iterator = 0;
                // No convergence yet
            } else {
                // Form shift
                $x = $this->H[$n][$n];
                $y = 0.0;
                $w = 0.0;
                if ($l < $n) {
                    $y = $this->H[$n - 1][$n - 1];
                    $w = $this->H[$n][$n - 1] * $this->H[$n - 1][$n];
                }
                // Wilkinson's original ad hoc shift
                if ($iterator == 10) {
                    $exShift += $x;
                    for ($i = $low; $i <= $n; ++$i) {
                        $this->H[$i][$i] -= $x;
                    }
                    $s = abs($this->H[$n][$n - 1]) + abs($this->H[$n - 1][$n - 2]);
                    $x = $y = 0.75 * $s;
                    $w = -0.4375 * $s * $s;
                }
                // MATLAB's new ad hoc shift
                if ($iterator == 30) {
                    $s = ($y - $x) / 2.0;
                    $s = $s * $s + $w;
                    if ($s > 0) {
                        $s = sqrt($s);
                        if ($y < $x) {
                            $s = -$s;
                        }
                        $s = $x - $w / (($y - $x) / 2.0 + $s);
                        for ($i = $low; $i <= $n; ++$i) {
                            $this->H[$i][$i] -= $s;
                        }
                        $exShift += $s;
                        $x = $y = $w = 0.964;
                    }
                }
                // Could check iteration count here.
                $iterator++;
                // Look for two consecutive small sub-diagonal elements
                $m = $n - 2;
                while ($m >= $l) {
                    $z = $this->H[$m][$m];
                    $r = $x - $z;
                    $s = $y - $z;
                    $p = ($r * $s - $w) / $this->H[$m + 1][$m] + $this->H[$m][$m + 1];
                    $q = $this->H[$m + 1][$m + 1] - $z - $r - $s;
                    $r = $this->H[$m + 2][$m + 1];
                    $s = abs($p) + abs($q) + abs($r);
                    $p = $p / $s;
                    $q = $q / $s;
                    $r = $r / $s;
                    if ($m == $l) {
                        break;
                    }
                    if (abs($this->H[$m][$m - 1]) * (abs($q) + abs($r)) <
                        $eps * (abs($p) * (abs($this->H[$m - 1][$m - 1]) + abs($z) + abs($this->H[$m + 1][$m + 1])))
                    ) {
                        break;
                    }
                    --$m;
                }
                for ($i = $m + 2; $i <= $n; ++$i) {
                    $this->H[$i][$i - 2] = 0.0;
                    if ($i > $m + 2) {
                        $this->H[$i][$i - 3] = 0.0;
                    }
                }
                // Double QR step involving rows l:n and columns m:n
                for ($k = $m; $k <= $n - 1; ++$k) {
                    $notLast = ($k != $n - 1);
                    if ($k != $m) {
                        $p = $this->H[$k][$k - 1];
                        $q = $this->H[$k + 1][$k - 1];
                        $r = ($notLast ? $this->H[$k + 2][$k - 1] : 0.0);
                        $x = abs($p) + abs($q) + abs($r);
                        if ($x != 0.0) {
                            $p = $p / $x;
                            $q = $q / $x;
                            $r = $r / $x;
                        }
                    }
                    if ($x == 0.0) {
                        break;
                    }
                    $s = sqrt($p * $p + $q * $q + $r * $r);
                    if ($p < 0) {
                        $s = -$s;
                    }
                    if ($s != 0) {
                        if ($k != $m) {
                            $this->H[$k][$k - 1] = -$s * $x;
                        } elseif ($l != $m) {
                            $this->H[$k][$k - 1] = -$this->H[$k][$k - 1];
                        }
                        $p = $p + $s;
                        $x = $p / $s;
                        $y = $q / $s;
                        $z = $r / $s;
                        $q = $q / $p;
                        $r = $r / $p;
                        // Row modification
                        for ($j = $k; $j < $nn; ++$j) {
                            $p = $this->H[$k][$j] + $q * $this->H[$k + 1][$j];
                            if ($notLast) {
                                $p = $p + $r * $this->H[$k + 2][$j];
                                $this->H[$k + 2][$j] = $this->H[$k + 2][$j] - $p * $z;
                            }
                            $this->H[$k][$j] = $this->H[$k][$j] - $p * $x;
                            $this->H[$k + 1][$j] = $this->H[$k + 1][$j] - $p * $y;
                        }
                        // Column modification
                        for ($i = 0; $i <= min($n, $k + 3); ++$i) {
                            $p = $x * $this->H[$i][$k] + $y * $this->H[$i][$k + 1];
                            if ($notLast) {
                                $p = $p + $z * $this->H[$i][$k + 2];
                                $this->H[$i][$k + 2] = $this->H[$i][$k + 2] - $p * $r;
                            }
                            $this->H[$i][$k] = $this->H[$i][$k] - $p;
                            $this->H[$i][$k + 1] = $this->H[$i][$k + 1] - $p * $q;
                        }
                        // Accumulate transformations
                        for ($i = $low; $i <= $high; ++$i) {
                            $p = $x * $this->V[$i][$k] + $y * $this->V[$i][$k + 1];
                            if ($notLast) {
                                $p = $p + $z * $this->V[$i][$k + 2];
                                $this->V[$i][$k + 2] = $this->V[$i][$k + 2] - $p * $r;
                            }
                            $this->V[$i][$k] = $this->V[$i][$k] - $p;
                            $this->V[$i][$k + 1] = $this->V[$i][$k + 1] - $p * $q;
                        }
                    }  // ($s != 0)
                }  // k loop
            }  // check convergence
        }  // while ($n >= $low)

        // Back-substitute to find vectors of upper triangular form
        if ($norm == 0.0) {
            return;
        }

        for ($n = $nn - 1; $n >= 0; --$n) {
            $p = $this->d[$n];
            $q = $this->e[$n];
            // Real vector
            if ($q == 0) {
                $l = $n;
                $this->H[$n][$n] = 1.0;
                for ($i = $n - 1; $i >= 0; --$i) {
                    $w = $this->H[$i][$i] - $p;
                    $r = 0.0;
                    for ($j = $l; $j <= $n; ++$j) {
                        $r = $r + $this->H[$i][$j] * $this->H[$j][$n];
                    }
                    if ($this->e[$i] < 0.0) {
                        $z = $w;
                        $s = $r;
                    } else {
                        $l = $i;
                        if ($this->e[$i] == 0.0) {
                            if ($w != 0.0) {
                                $this->H[$i][$n] = -$r / $w;
                            } else {
                                $this->H[$i][$n] = -$r / ($eps * $norm);
                            }
                            // Solve real equations
                        } else {
                            $x = $this->H[$i][$i + 1];
                            $y = $this->H[$i + 1][$i];
                            $q = ($this->d[$i] - $p) * ($this->d[$i] - $p) + $this->e[$i] * $this->e[$i];
                            $t = ($x * $s - $z * $r) / $q;
                            $this->H[$i][$n] = $t;
                            if (abs($x) > abs($z)) {
                                $this->H[$i + 1][$n] = (-$r - $w * $t) / $x;
                            } else {
                                $this->H[$i + 1][$n] = (-$s - $y * $t) / $z;
                            }
                        }
                        // Overflow control
                        $t = abs($this->H[$i][$n]);
                        if (($eps * $t) * $t > 1) {
                            for ($j = $i; $j <= $n; ++$j) {
                                $this->H[$j][$n] = $this->H[$j][$n] / $t;
                            }
                        }
                    }
                }
                // Complex vector
            } elseif ($q < 0) {
                $l = $n - 1;
                // Last vector component imaginary so matrix is triangular
                if (abs($this->H[$n][$n - 1]) > abs($this->H[$n - 1][$n])) {
                    $this->H[$n - 1][$n - 1] = $q / $this->H[$n][$n - 1];
                    $this->H[$n - 1][$n] = -($this->H[$n][$n] - $p) / $this->H[$n][$n - 1];
                } else {
                    $this->cDiv(0.0, -$this->H[$n - 1][$n], $this->H[$n - 1][$n - 1] - $p, $q);
                    $this->H[$n - 1][$n - 1] = $this->cDivR;
                    $this->H[$n - 1][$n] = $this->cDivI;
                }
                $this->H[$n][$n - 1] = 0.0;
                $this->H[$n][$n] = 1.0;
                for ($i = $n - 2; $i >= 0; --$i) {
                    // double ra,sa,vr,vi;
                    $ra = 0.0;
                    $sa = 0.0;
                    for ($j = $l; $j <= $n; ++$j) {
                        $ra = $ra + $this->H[$i][$j] * $this->H[$j][$n - 1];
                        $sa = $sa + $this->H[$i][$j] * $this->H[$j][$n];
                    }
                    $w = $this->H[$i][$i] - $p;
                    if ($this->e[$i] < 0.0) {
                        $z = $w;
                        $r = $ra;
                        $s = $sa;
                    } else {
                        $l = $i;
                        if ($this->e[$i] == 0) {
                            $this->cDiv(-$ra, -$sa, $w, $q);
                            $this->H[$i][$n - 1] = $this->cDivR;
                            $this->H[$i][$n] = $this->cDivI;
                        } else {
                            // Solve complex equations
                            $x = $this->H[$i][$i + 1];
                            $y = $this->H[$i + 1][$i];
                            $vr = ($this->d[$i] - $p) * ($this->d[$i] - $p) + $this->e[$i] * $this->e[$i] - $q * $q;
                            $vi = ($this->d[$i] - $p) * 2.0 * $q;
                            if ($vr == 0.0 & $vi == 0.0) {
                                $vr = $eps * $norm * (abs($w) + abs($q) + abs($x) + abs($y) + abs($z));
                            }
                            $this->cDiv($x * $r - $z * $ra + $q * $sa, $x * $s - $z * $sa - $q * $ra, $vr, $vi);
                            $this->H[$i][$n - 1] = $this->cDivR;
                            $this->H[$i][$n] = $this->cDivI;
                            if (abs($x) > (abs($z) + abs($q))) {
                                $this->H[$i + 1][$n - 1] = (-$ra - $w * $this->H[$i][$n - 1] + $q * $this->H[$i][$n]) / $x;
                                $this->H[$i + 1][$n] = (-$sa - $w * $this->H[$i][$n] - $q * $this->H[$i][$n - 1]) / $x;
                            } else {
                                $this->cDiv(-$r - $y * $this->H[$i][$n - 1], -$s - $y * $this->H[$i][$n], $z, $q);
                                $this->H[$i + 1][$n - 1] = $this->cDivR;
                                $this->H[$i + 1][$n] = $this->cDivI;
                            }
                        }
                        // Overflow control
                        $t = max(abs($this->H[$i][$n - 1]), abs($this->H[$i][$n]));
                        if (($eps * $t) * $t > 1) {
                            for ($j = $i; $j <= $n; ++$j) {
                                $this->H[$j][$n - 1] = $this->H[$j][$n - 1] / $t;
                                $this->H[$j][$n] = $this->H[$j][$n] / $t;
                            }
                        }
                    } // end else
                } // end for
            } // end else for complex case
        } // end for

        // Vectors of isolated roots
        for ($i = 0; $i < $nn; ++$i) {
            if ($i < $low | $i > $high) {
                for ($j = $i; $j < $nn; ++$j) {
                    $this->V[$i][$j] = $this->H[$i][$j];
                }
            }
        }

        // Back transformation to get eigen vectors of original matrix
        for ($j = $nn - 1; $j >= $low; --$j) {
            for ($i = $low; $i <= $high; ++$i) {
                $z = 0.0;
                for ($k = $low; $k <= min($j, $high); ++$k) {
                    $z = $z + $this->V[$i][$k] * $this->H[$k][$j];
                }
                $this->V[$i][$j] = $z;
            }
        }
    }

    /**
     *    Constructor: Check for symmetry, then construct the eigenvalue decomposition
     *
     * @param Matrix $Arg
     * @internal param Matrix $Arg Square matrix
     */
    public function __construct($Arg) {
        $this->A = $Arg->getArray();
        $this->n = $Arg->getColumnDimension();

        $isSymmetric = true;
        for ($j = 0; ($j < $this->n) & $isSymmetric; ++$j) {
            for ($i = 0; ($i < $this->n) & $isSymmetric; ++$i) {
                $isSymmetric = ($this->A[$i][$j] == $this->A[$j][$i]);
            }
        }

        if ($isSymmetric) {
            $this->V = $this->A;
            // Tri-diagonalize.
            $this->tRed();
            // Diagonalize.
            $this->tql2();
        } else {
            $this->H = $this->A;
            $this->ort = array();
            // Reduce to Hessenberg form.
            $this->ortHes();
            // Reduce Hessenberg to real Schur form.
            $this->hqr2();
        }
    }

    /**
     *    Return the eigen vector matrix
     *
     * @access public
     * @return Matrix V
     */
    public function getV() {
        return new Matrix($this->V, $this->n, $this->n);
    }

    /**
     *    Return the real parts of the eigenvalues
     *
     * @access public
     * @return array(diagonal(D))
     */
    public function getRealEigenvalues() {
        return $this->d;
    }

    /**
     *    Return the imaginary parts of the eigenvalues
     *
     * @access public
     * @return array(diagonal(D))
     */
    public function getImagEigenvalues() {
        return $this->e;
    }

    /**
     *    Return the block diagonal eigenvalue matrix
     *
     * @access public
     * @return Matrix D
     */
    public function getD() {
        $D = [];
        for ($i = 0; $i < $this->n; ++$i) {
            $D[$i] = array_fill(0, $this->n, 0.0);
            $D[$i][$i] = $this->d[$i];
            if ($this->e[$i] == 0) {
                continue;
            }
            $o = ($this->e[$i] > 0) ? $i + 1 : $i - 1;
            $D[$i][$o] = $this->e[$i];
        }
        return new Matrix($D);
    }
}
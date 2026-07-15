<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Garantía - Productos Paraíso</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; color: #333; }
        .container { max-width: 480px; margin: 0 auto; background: #fff; min-height: 100vh; }
        .header { background: #8B0000; color: #fff; padding: 20px; text-align: center; }
        .header h1 { font-size: 24px; letter-spacing: 2px; }
        .header p { font-size: 11px; margin-top: 4px; opacity: 0.9; }
        .product-bar { background: #f9f9f9; padding: 12px 16px; border-bottom: 1px solid #eee; }
        .product-bar .prod-row { display: flex; justify-content: space-between; align-items: center; }
        .product-bar .prod-label { font-size: 11px; color: #666; }
        .product-bar .prod-value { font-size: 13px; font-weight: bold; color: #333; }
        .product-bar .serial { font-family: monospace; font-size: 12px; color: #8B0000; font-weight: bold; }
        .content { padding: 20px; }
        .form-title { font-size: 18px; font-weight: bold; color: #333; margin-bottom: 4px; }
        .form-subtitle { font-size: 12px; color: #666; margin-bottom: 20px; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 13px; font-weight: bold; color: #333; margin-bottom: 4px; }
        .form-group label .optional { font-weight: normal; color: #999; font-size: 11px; }
        .form-control { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; color: #333; background: #fff; }
        .form-control:focus { outline: none; border-color: #8B0000; box-shadow: 0 0 0 2px rgba(139,0,0,0.1); }
        select.form-control { appearance: auto; }
        .form-error { font-size: 12px; color: #dc3545; margin-top: 4px; }
        .form-check { display: flex; align-items: flex-start; gap: 8px; margin-bottom: 16px; }
        .form-check input { margin-top: 2px; }
        .form-check label { font-size: 12px; color: #666; line-height: 1.4; }
        .btn { display: block; width: 100%; padding: 16px; background: #8B0000; color: #fff; border: none; border-radius: 8px; font-size: 16px; font-weight: bold; text-align: center; text-decoration: none; cursor: pointer; }
        .btn:hover { background: #6B0000; }
        .btn:disabled { opacity: 0.6; cursor: not-allowed; }
        .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; font-size: 13px; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .section-divider { height: 1px; background: #eee; margin: 20px 0; }
        .section-label { font-size: 12px; color: #8B0000; text-transform: uppercase; letter-spacing: 1px; font-weight: bold; margin-bottom: 16px; }
        .footer { padding: 20px; text-align: center; font-size: 11px; color: #999; border-top: 1px solid #eee; }
        .row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    </style>
    @include('partials.pwa')
</head>
<body>
<div class="container">

    <div class="header">
        <h1>PARAÍSO</h1>
        <p>REGISTRO DE GARANTÍA</p>
    </div>

    <div class="product-bar">
        @php $imageUrl = $label->product->image ? \Illuminate\Support\Facades\Storage::url($label->product->image) : null; @endphp

        @if($imageUrl)
        <div style="text-align:center; margin-bottom:12px;">
            <img src="{{ $imageUrl }}" alt="{{ $label->product->name }}"
                 style="max-width:100%; height:auto; max-height:180px; border-radius:8px; object-fit:contain;"
                 onerror="this.style.display='none'">
        </div>
        @endif

        <div class="prod-row">
            <div>
                <div class="prod-label">Producto</div>
                <div class="prod-value">{{ $label->product->name }}</div>
            </div>
            <div style="text-align:right">
                <div class="prod-label">Serial</div>
                <div class="serial">{{ $label->serial }}</div>
            </div>
        </div>
        <div style="display:flex; gap:16px; margin-top:8px; font-size:12px; color:#666;">
            <span>Modelo: {{ $label->product->productModel->name }}</span>
            <span>Medidas: {{ $label->product->measurements_text }}</span>
            <span>Garantía: {{ $label->product->productModel->warranty_years }} años</span>
        </div>
    </div>

    <div class="content">

        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="form-title">Datos del cliente</div>
        <div class="form-subtitle">Complete todos los campos obligatorios (*)</div>

        <form method="POST" action="{{ route('public.warranty.store', $label->serial) }}">
            @csrf

            <div class="section-label">Información personal</div>

            <div class="form-group">
                <label for="first_name">Primer nombre *</label>
                <input type="text" id="first_name" name="first_name" class="form-control" value="{{ old('first_name') }}" maxlength="100" required>
                @error('first_name') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label for="second_name">Segundo nombre <span class="optional">(opcional)</span></label>
                <input type="text" id="second_name" name="second_name" class="form-control" value="{{ old('second_name') }}" maxlength="100">
                @error('second_name') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label for="last_name">Primer apellido *</label>
                <input type="text" id="last_name" name="last_name" class="form-control" value="{{ old('last_name') }}" maxlength="100" required>
                @error('last_name') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label for="second_last_name">Segundo apellido <span class="optional">(opcional)</span></label>
                <input type="text" id="second_last_name" name="second_last_name" class="form-control" value="{{ old('second_last_name') }}" maxlength="100">
                @error('second_last_name') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            <div class="row-2">
                <div class="form-group">
                    <label for="document_type">Tipo de documento *</label>
                    <select id="document_type" name="document_type" class="form-control" required>
                        <option value="">Seleccione...</option>
                        <option value="cedula" {{ old('document_type') === 'cedula' ? 'selected' : '' }}>Cédula</option>
                        <option value="ruc" {{ old('document_type') === 'ruc' ? 'selected' : '' }}>RUC</option>
                        <option value="pasaporte" {{ old('document_type') === 'pasaporte' ? 'selected' : '' }}>Pasaporte</option>
                    </select>
                    @error('document_type') <div class="form-error">{{ $message }}</div> @enderror
                </div>

                <div class="form-group">
                    <label for="document_number">Número de documento *</label>
                    <input type="text" id="document_number" name="document_number" class="form-control" value="{{ old('document_number') }}" maxlength="20" required>
                    @error('document_number') <div class="form-error">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="row-2">
                <div class="form-group">
                    <label for="birth_date">Fecha de nacimiento <span class="optional">(opcional)</span></label>
                    <input type="date" id="birth_date" name="birth_date" class="form-control" value="{{ old('birth_date') }}">
                    @error('birth_date') <div class="form-error">{{ $message }}</div> @enderror
                </div>

                <div class="form-group">
                    <label for="gender">Género <span class="optional">(opcional)</span></label>
                    <select id="gender" name="gender" class="form-control">
                        <option value="">Seleccione...</option>
                        <option value="masculino" {{ old('gender') === 'masculino' ? 'selected' : '' }}>Masculino</option>
                        <option value="femenino" {{ old('gender') === 'femenino' ? 'selected' : '' }}>Femenino</option>
                        <option value="otro" {{ old('gender') === 'otro' ? 'selected' : '' }}>Otro</option>
                    </select>
                    @error('gender') <div class="form-error">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="section-divider"></div>
            <div class="section-label">Contacto</div>

            <div class="form-group">
                <label for="email">Correo electrónico *</label>
                <input type="email" id="email" name="email" class="form-control" value="{{ old('email') }}" maxlength="255" required>
                @error('email') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label for="phone">Celular *</label>
                <input type="text" id="phone" name="phone" class="form-control" value="{{ old('phone') }}" maxlength="20" required>
                @error('phone') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label for="address">Dirección *</label>
                <input type="text" id="address" name="address" class="form-control" value="{{ old('address') }}" maxlength="255" required>
                @error('address') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            <div class="row-2">
                <div class="form-group">
                    <label for="province">Provincia *</label>
                    <select id="province" name="province" class="form-control" required>
                        <option value="">Seleccioná una provincia...</option>
                        @foreach(App\Data\EcuadorGeographicData::provinceList() as $province)
                            <option value="{{ $province }}" {{ old('province') === $province ? 'selected' : '' }}>{{ $province }}</option>
                        @endforeach
                    </select>
                    @error('province') <div class="form-error">{{ $message }}</div> @enderror
                </div>

                <div class="form-group">
                    <label for="city">Ciudad *</label>
                    <input
                        type="text"
                        id="city"
                        name="city"
                        class="form-control"
                        value="{{ old('city') }}"
                        placeholder="Escribí tu ciudad"
                        maxlength="100"
                        required
                    >
                    @error('city') <div class="form-error">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="form-group">
                <label for="sector">Sector <span class="optional">(opcional)</span></label>
                <input type="text" id="sector" name="sector" class="form-control" value="{{ old('sector') }}" maxlength="100">
                @error('sector') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            <div class="section-divider"></div>
            <div class="section-label">Datos de la compra</div>

            <div class="form-group">
                <label for="store_name">Local de compra *</label>
                <input type="text" id="store_name" name="store_name" class="form-control" value="{{ old('store_name') }}" maxlength="255" required>
                @error('store_name') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label for="invoice_number">Número de factura *</label>
                <input type="text" id="invoice_number" name="invoice_number" class="form-control" value="{{ old('invoice_number') }}" maxlength="100" required>
                @error('invoice_number') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            <p style="font-size:12px; color:#666; margin-top:4px;">Fecha de compra: se registra automáticamente la fecha actual.</p>

            <div class="section-divider"></div>

            <div class="form-check">
                <input type="checkbox" id="terms_accepted" name="terms_accepted" value="1" {{ old('terms_accepted') ? 'checked' : '' }} required>
                <label for="terms_accepted">
                    He leído y acepto los <strong>Términos y Condiciones</strong> de la garantía. Declaro que la información proporcionada es verídica y que el producto fue adquirido en el local indicado.
                </label>
            </div>
            @error('terms_accepted') <div class="form-error" style="margin-top:-12px; margin-bottom:16px;">{{ $message }}</div> @enderror

            <button type="submit" class="btn">Registrar garantía</button>

        </form>

    </div>

    <div class="footer">
        <p>Productos Paraíso del Ecuador</p>
        <p>www.paraiso.com.ec</p>
    </div>

</div>
</body>
</html>

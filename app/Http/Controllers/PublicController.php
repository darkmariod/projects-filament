<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Label;
use App\Models\LabelLog;
use App\Models\Warranty;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class PublicController extends Controller
{
    public function qrImage(string $serial)
    {
        $label = Label::where('serial', $serial)->firstOrFail();

        $qrSvg = QrCode::format('svg')
            ->size(250)
            ->generate($label->qr_url);

        return response($qrSvg)
            ->header('Content-Type', 'image/svg+xml')
            ->header('Cache-Control', 'public, max-age=86400');
    }

    public function product(string $serial)
    {
        $label = Label::where('serial', $serial)
            ->with([
                'product.productModel.category',
                'product.technicalComposition',
                'labelBatch',
                'warranty.customer',
            ])
            ->firstOrFail();

        return view('public.product', compact('label'));
    }

    public function warrantyForm(string $serial)
    {
        $label = Label::where('serial', $serial)
            ->with(['product.productModel', 'labelBatch'])
            ->firstOrFail();

        if ($label->status === 'registered') {
            return redirect()->route('public.product', $serial)
                ->with('error', 'Esta garantía ya fue registrada.');
        }

        if ($label->status === 'anulled') {
            return redirect()->route('public.product', $serial)
                ->with('error', 'Esta etiqueta ha sido anulada.');
        }

        return view('public.warranty-form', compact('label'));
    }

    public function warrantyStore(Request $request, string $serial)
    {
        $label = Label::where('serial', $serial)
            ->with(['product.productModel'])
            ->firstOrFail();

        if ($label->status === 'registered') {
            return redirect()->route('public.product', $serial)
                ->with('error', 'Esta garantía ya fue registrada.');
        }

        if ($label->status === 'anulled') {
            return redirect()->route('public.product', $serial)
                ->with('error', 'Esta etiqueta ha sido anulada.');
        }

        $request->validate([
            'first_name'       => 'required|string|max:100',
            'second_name'      => 'nullable|string|max:100',
            'last_name'        => 'required|string|max:100',
            'second_last_name' => 'nullable|string|max:100',
            'document_type'    => 'required|in:cedula,ruc,pasaporte',
            'document_number'  => 'required|string|max:20',
            'birth_date'       => 'nullable|date',
            'gender'           => 'nullable|in:masculino,femenino,otro',
            'email'            => 'required|email|max:255',
            'phone'            => 'required|string|max:20',
            'address'          => 'required|string|max:255',
            'province'         => 'required|string|max:100',
            'city'             => 'required|string|max:100',
            'sector'           => 'nullable|string|max:100',
            'store_name'       => 'required|string|max:255',
            'invoice_number'   => 'required|string|max:100',
            'purchase_date'    => 'required|date',
            'terms_accepted'   => 'required|accepted',
        ], [
            'first_name.required'      => 'El primer nombre es obligatorio.',
            'last_name.required'       => 'El primer apellido es obligatorio.',
            'document_type.required'   => 'El tipo de documento es obligatorio.',
            'document_number.required' => 'El número de documento es obligatorio.',
            'email.required'           => 'El correo electrónico es obligatorio.',
            'email.email'              => 'El correo electrónico no es válido.',
            'phone.required'           => 'El celular es obligatorio.',
            'address.required'         => 'La dirección es obligatoria.',
            'province.required'        => 'La provincia es obligatoria.',
            'city.required'            => 'La ciudad es obligatoria.',
            'store_name.required'      => 'El local de compra es obligatorio.',
            'invoice_number.required'  => 'El número de factura es obligatorio.',
            'purchase_date.required'   => 'La fecha de compra es obligatoria.',
            'terms_accepted.accepted'  => 'Debe aceptar los términos y condiciones.',
        ]);

        DB::transaction(function () use ($request, $label) {
            $customer = Customer::firstOrCreate(
                ['document_number' => $request->document_number],
                [
                    'first_name'       => $request->first_name,
                    'second_name'      => $request->second_name,
                    'last_name'        => $request->last_name,
                    'second_last_name' => $request->second_last_name,
                    'document_type'    => $request->document_type,
                    'birth_date'       => $request->birth_date,
                    'gender'           => $request->gender,
                    'email'            => $request->email,
                    'phone'            => $request->phone,
                    'address'          => $request->address,
                    'province'         => $request->province,
                    'city'             => $request->city,
                    'sector'           => $request->sector,
                ]
            );

            $purchaseDate    = \Carbon\Carbon::parse($request->purchase_date);
            $warrantyYears   = $label->product->productModel->warranty_years ?? 1;
            $warrantyStart   = $purchaseDate->copy();
            $warrantyEnd     = $purchaseDate->copy()->addYears($warrantyYears);

            Warranty::create([
                'label_id'            => $label->id,
                'customer_id'         => $customer->id,
                'store_name'          => $request->store_name,
                'invoice_number'      => $request->invoice_number,
                'purchase_date'       => $purchaseDate,
                'warranty_start_date' => $warrantyStart,
                'warranty_end_date'   => $warrantyEnd,
                'status'              => 'active',
                'terms_accepted'      => true,
            ]);

            $label->update([
                'status'        => 'registered',
                'registered_at' => now(),
            ]);

            LabelLog::create([
                'label_id'       => $label->id,
                'label_batch_id' => $label->label_batch_id,
                'user_id'        => 1,
                'action'         => 'registrar_garantia',
                'description'    => "Garantía registrada para serial {$label->serial} por cliente {$customer->first_name} {$customer->last_name}.",
                'ip'             => request()->ip(),
                'created_at'     => now(),
            ]);
        });

        return redirect()->route('public.warranty.certificate', $serial);
    }

    public function warrantyCertificate(string $serial)
    {
        $label = Label::where('serial', $serial)
            ->with([
                'product.productModel',
                'product.technicalComposition',
                'labelBatch',
                'warranty.customer',
            ])
            ->firstOrFail();

        if (!$label->warranty) {
            return redirect()->route('public.product', $serial);
        }

        if (request()->query('download') === '1') {
            $pdf = Pdf::loadView('public.certificate-pdf', compact('label'))
                ->setPaper('a4', 'portrait');

            return $pdf->download('certificado-garantia-' . $serial . '.pdf');
        }

        return view('public.warranty-confirm', compact('label'));
    }
}

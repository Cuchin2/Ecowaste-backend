<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Permite la petición. Si usas auth, puedes cambiarlo a: return auth()->check();
        return true; 
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // --- DATOS DE FACTURACIÓN (Obligatorios) ---
            'user_id' => 'nullable|integer|exists:users,id', // Nullable por si es checkout como invitado
            'name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'business' => 'nullable|string|max:255',
            'document_type' => ['required', Rule::in(['DNI', 'RUC', 'CE', 'PASAPORTE'])],
            'dni' => 'required|string|min:8|max:20', // DNI=8, RUC=11, CE/Pasaporte varían
            'phone' => 'required|string|min:9|max:15',
            'email' => 'required|email|max:255',
            'address' => 'required|string|max:255',
            'reference' => 'nullable|string|max:255',
            'total' => 'required|numeric|min:0',
            
            // Ubicación de facturación
            'country' => 'required|string',
            'state' => 'required|string',
            'city' => 'required|string',
            'district' => 'required|string',
            'zip_code' => 'nullable|string|max:20',

            // --- CONTROL DE ENVÍO ---
            'otra' => 'required|in:true,false', // Viene como string desde el frontend

            // --- DATOS DE ENVÍO (Condicionales: solo si 'otra' == 'true') ---
            'name2' => 'required_if:otra,true|string|max:255',
            'last_name2' => 'required_if:otra,true|string|max:255',
            'address2' => 'required_if:otra,true|string|max:255',
            'reference2' => 'nullable|string|max:255',
            'country2' => 'required_if:otra,true|string',
            'state2' => 'required_if:otra,true|string',
            'city2' => 'required_if:otra,true|string',
            'district2' => 'required_if:otra,true|string',
        ];
    }

    /**
     * Mensajes de error personalizados en español.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'last_name.required' => 'El apellido es obligatorio.',
            'document_type.in' => 'Selecciona un tipo de documento válido.',
            'dni.min' => 'El documento debe tener al menos 8 caracteres.',
            'phone.min' => 'El teléfono es obligatorio y debe ser válido.',
            'email.email' => 'Ingresa un correo electrónico válido.',
            'address.required' => 'La dirección es obligatoria.',
            'total.numeric' => 'El total debe ser un número válido.',
            
            // Mensajes condicionales para envío
            'name2.required_if' => 'El nombre de envío es obligatorio cuando se usa una dirección diferente.',
            'last_name2.required_if' => 'El apellido de envío es obligatorio.',
            'address2.required_if' => 'La dirección de envío es obligatoria.',
            'city2.required_if' => 'La ciudad de envío es obligatoria.',
            'district2.required_if' => 'El distrito de envío es obligatorio.',
        ];
    }
}
package com.kyusui.app.ui.customer.payment;

import androidx.lifecycle.SavedStateHandle;
import com.kyusui.app.data.upload.ProofFileSource;
import com.kyusui.app.domain.repository.CustomerRepository;
import dagger.internal.DaggerGenerated;
import dagger.internal.Factory;
import dagger.internal.QualifierMetadata;
import dagger.internal.ScopeMetadata;
import javax.annotation.processing.Generated;
import javax.inject.Provider;

@ScopeMetadata
@QualifierMetadata
@DaggerGenerated
@Generated(
    value = "dagger.internal.codegen.ComponentProcessor",
    comments = "https://dagger.dev"
)
@SuppressWarnings({
    "unchecked",
    "rawtypes",
    "KotlinInternal",
    "KotlinInternalInJava"
})
public final class PaymentViewModel_Factory implements Factory<PaymentViewModel> {
  private final Provider<SavedStateHandle> savedStateHandleProvider;

  private final Provider<CustomerRepository> customerRepositoryProvider;

  private final Provider<ProofFileSource> proofFileSourceProvider;

  public PaymentViewModel_Factory(Provider<SavedStateHandle> savedStateHandleProvider,
      Provider<CustomerRepository> customerRepositoryProvider,
      Provider<ProofFileSource> proofFileSourceProvider) {
    this.savedStateHandleProvider = savedStateHandleProvider;
    this.customerRepositoryProvider = customerRepositoryProvider;
    this.proofFileSourceProvider = proofFileSourceProvider;
  }

  @Override
  public PaymentViewModel get() {
    return newInstance(savedStateHandleProvider.get(), customerRepositoryProvider.get(), proofFileSourceProvider.get());
  }

  public static PaymentViewModel_Factory create(Provider<SavedStateHandle> savedStateHandleProvider,
      Provider<CustomerRepository> customerRepositoryProvider,
      Provider<ProofFileSource> proofFileSourceProvider) {
    return new PaymentViewModel_Factory(savedStateHandleProvider, customerRepositoryProvider, proofFileSourceProvider);
  }

  public static PaymentViewModel newInstance(SavedStateHandle savedStateHandle,
      CustomerRepository customerRepository, ProofFileSource proofFileSource) {
    return new PaymentViewModel(savedStateHandle, customerRepository, proofFileSource);
  }
}

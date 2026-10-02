package com.kyusui.app.data.repository;

import com.kyusui.app.data.api.CustomerApi;
import com.kyusui.app.data.upload.ProofFileSource;
import dagger.internal.DaggerGenerated;
import dagger.internal.Factory;
import dagger.internal.QualifierMetadata;
import dagger.internal.ScopeMetadata;
import javax.annotation.processing.Generated;
import javax.inject.Provider;

@ScopeMetadata("javax.inject.Singleton")
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
public final class CustomerRepositoryImpl_Factory implements Factory<CustomerRepositoryImpl> {
  private final Provider<CustomerApi> customerApiProvider;

  private final Provider<ProofFileSource> proofFileSourceProvider;

  public CustomerRepositoryImpl_Factory(Provider<CustomerApi> customerApiProvider,
      Provider<ProofFileSource> proofFileSourceProvider) {
    this.customerApiProvider = customerApiProvider;
    this.proofFileSourceProvider = proofFileSourceProvider;
  }

  @Override
  public CustomerRepositoryImpl get() {
    return newInstance(customerApiProvider.get(), proofFileSourceProvider.get());
  }

  public static CustomerRepositoryImpl_Factory create(Provider<CustomerApi> customerApiProvider,
      Provider<ProofFileSource> proofFileSourceProvider) {
    return new CustomerRepositoryImpl_Factory(customerApiProvider, proofFileSourceProvider);
  }

  public static CustomerRepositoryImpl newInstance(CustomerApi customerApi,
      ProofFileSource proofFileSource) {
    return new CustomerRepositoryImpl(customerApi, proofFileSource);
  }
}
